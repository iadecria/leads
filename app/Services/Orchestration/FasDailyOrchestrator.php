<?php

namespace App\Services\Orchestration;

use App\Models\FasExecutionRun;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FasDailyOrchestrator
{
    /**
     * Executes the daily pipeline for a specific run.
     */
    public function execute(FasExecutionRun $run): void
    {
        // 1. Lock check
        if ($this->isAnotherRunActive($run)) {
            $this->markAsFailed($run, 'Another daily execution is already running for this date.');

            return;
        }

        try {
            $run->update([
                'status' => 'RUNNING',
                'started_at' => now(),
                'current_step' => 'fixtures',
            ]);

            $date = $run->analysis_date->format('Y-m-d');
            $summary = $run->summary ?? [];

            // Step 1: Sync Fixtures
            if ($run->fixtures_status !== 'COMPLETED') {
                $run->update(['current_step' => 'fixtures']);
                $exitCode = Artisan::call('fas:sync-fixtures', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:sync-fixtures failed with exit code {$exitCode}");
                }

                $output = Artisan::output();
                preg_match('/Elegíveis:\s*(\d+)/', $output, $matchesEligible);
                preg_match('/Encontrados:\s*(\d+)/', $output, $matchesFound);

                $summary['fixtures_eligible'] = isset($matchesEligible[1]) ? (int) $matchesEligible[1] : 0;
                $summary['fixtures_found'] = isset($matchesFound[1]) ? (int) $matchesFound[1] : 0;

                $run->update(['fixtures_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 2: Build Datasets
            if ($run->datasets_status !== 'COMPLETED') {
                $run->update(['current_step' => 'datasets']);
                $exitCode = Artisan::call('fas:build-dataset', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:build-dataset failed with exit code {$exitCode}");
                }

                $output = Artisan::output();
                preg_match('/Gerados:\s*(\d+)/', $output, $matches);
                $summary['datasets_generated'] = isset($matches[1]) ? (int) $matches[1] : 0;

                $run->update(['datasets_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 3: Run Analysis
            if ($run->analysis_status !== 'COMPLETED') {
                $run->update(['current_step' => 'analysis']);
                $exitCode = Artisan::call('fas:analyze', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:analyze failed with exit code {$exitCode}");
                }

                $output = Artisan::output();
                preg_match('/Total Analyzed:\s*(\d+)/', $output, $matches);
                $summary['analyses_generated'] = isset($matches[1]) ? (int) $matches[1] : 0;

                $run->update(['analysis_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 4: Generate Ranking
            if ($run->ranking_status !== 'COMPLETED') {
                $run->update(['current_step' => 'ranking']);
                $exitCode = Artisan::call('fas:rank', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:rank failed with exit code {$exitCode}");
                }

                $output = Artisan::output();
                preg_match('/TOP 3:\s*(\d+)/', $output, $top3Matches);
                preg_match('/TOP 5:\s*(\d+)/', $output, $top5Matches);
                preg_match('/Watchlist:\s*(\d+)/', $output, $watchlistMatches);

                $summary['top3_count'] = isset($top3Matches[1]) ? (int) $top3Matches[1] : 0;
                $summary['top5_count'] = isset($top5Matches[1]) ? (int) $top5Matches[1] : 0;
                $summary['watchlist_count'] = isset($watchlistMatches[1]) ? (int) $watchlistMatches[1] : 0;

                $run->update(['ranking_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Finish
            $run->update([
                'status' => 'COMPLETED',
                'finished_at' => now(),
                'current_step' => 'done',
            ]);

        } catch (Exception $e) {
            Log::error('FasDailyOrchestrator failed: '.$e->getMessage());

            $errors = $run->errors ?? [];
            $errors[] = [
                'step' => $run->current_step,
                'message' => $e->getMessage(),
                'time' => now()->toDateTimeString(),
            ];

            // Mark the current failing step status
            $statusField = "{$run->current_step}_status";
            $run->update([
                $statusField => 'FAILED',
                'status' => 'FAILED',
                'errors' => $errors,
                'finished_at' => now(),
            ]);
        }
    }

    private function isAnotherRunActive(FasExecutionRun $run): bool
    {
        return FasExecutionRun::where('execution_type', 'DAILY_ANALYSIS')
            ->whereDate('analysis_date', $run->analysis_date)
            ->where('id', '!=', $run->id)
            ->where('status', 'RUNNING')
            ->exists();
    }

    private function markAsFailed(FasExecutionRun $run, string $message): void
    {
        $run->update([
            'status' => 'FAILED',
            'finished_at' => now(),
            'errors' => [['message' => $message, 'time' => now()->toDateTimeString()]],
        ]);
    }
}
