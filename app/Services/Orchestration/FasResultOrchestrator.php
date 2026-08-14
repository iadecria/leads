<?php

namespace App\Services\Orchestration;

use App\Models\FasExecutionRun;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FasResultOrchestrator
{
    /**
     * Executes the result audit pipeline for a specific run.
     */
    public function execute(FasExecutionRun $run): void
    {
        // 1. Check if date is in the future
        if ($run->analysis_date->isFuture()) {
            $this->markAsFailed($run, 'Não há resultados a conferir para uma data futura.');

            return;
        }

        // 2. Lock check
        if ($this->isAnotherRunActive($run)) {
            $this->markAsFailed($run, 'Another audit execution is already running for this date.');

            return;
        }

        try {
            $run->update([
                'status' => 'RUNNING',
                'started_at' => now(),
                'current_step' => 'results',
            ]);

            $date = $run->analysis_date->format('Y-m-d');
            $summary = $run->summary ?? [];

            // Step 1: Sync Results
            if ($run->results_status !== 'COMPLETED') {
                $run->update(['current_step' => 'results']);
                $exitCode = Artisan::call('fas:sync-results', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:sync-results failed with exit code {$exitCode}");
                }

                $output = Artisan::output();
                preg_match('/Resultados Sincronizados:\s*(\d+)/', $output, $matchesResults);

                $summary['fixtures_finished'] = isset($matchesResults[1]) ? (int) $matchesResults[1] : 0;

                $run->update(['results_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 2: Audit
            if ($run->audit_status !== 'COMPLETED') {
                $run->update(['current_step' => 'audit']);
                // Dispatch fas:audit with force flag if we want it, but usually standard run is fine
                $exitCode = Artisan::call('fas:audit', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:audit failed with exit code {$exitCode}");
                }

                $output = Artisan::output();
                preg_match('/HIT:\s*(\d+)/', $output, $hitMatches);
                preg_match('/MISS:\s*(\d+)/', $output, $missMatches);
                preg_match('/PENDING:\s*(\d+)/', $output, $pendingMatches);
                preg_match('/UNAVAILABLE:\s*(\d+)/', $output, $unavailableMatches);

                $summary['audits_hit'] = isset($hitMatches[1]) ? (int) $hitMatches[1] : 0;
                $summary['audits_miss'] = isset($missMatches[1]) ? (int) $missMatches[1] : 0;
                $summary['audits_pending'] = isset($pendingMatches[1]) ? (int) $pendingMatches[1] : 0;
                $summary['audits_unavailable'] = isset($unavailableMatches[1]) ? (int) $unavailableMatches[1] : 0;

                $run->update(['audit_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 3: Clear Performance Cache (Implementation placeholder)
            // cache()->tags('fas_performance')->flush();

            // Finish
            $run->update([
                'status' => 'COMPLETED',
                'finished_at' => now(),
                'current_step' => 'done',
            ]);

        } catch (Exception $e) {
            Log::error('FasResultOrchestrator failed: '.$e->getMessage());

            $errors = $run->errors ?? [];
            $errors[] = [
                'step' => $run->current_step,
                'message' => $e->getMessage(),
                'time' => now()->toDateTimeString(),
            ];

            // Mark the current failing step status
            $statusField = "{$run->current_step}_status";
            if (in_array($statusField, ['results_status', 'audit_status'])) {
                $run->update([$statusField => 'FAILED']);
            }

            $run->update([
                'status' => 'FAILED',
                'errors' => $errors,
                'finished_at' => now(),
            ]);
        }
    }

    private function isAnotherRunActive(FasExecutionRun $run): bool
    {
        return FasExecutionRun::where('execution_type', 'RESULT_AUDIT')
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
