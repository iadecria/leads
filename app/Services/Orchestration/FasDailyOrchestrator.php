<?php

namespace App\Services\Orchestration;

use App\Models\FasExecutionRun;
use App\Models\FasAnalysis;
use App\Models\MatchDatasetRecord;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use App\Services\OpenRouter\OpenRouterResearchService;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FasDailyOrchestrator
{
    /**
     * Executes the daily pipeline for a specific run.
     */
    public function execute(FasExecutionRun $run, ?OpenRouterResearchService $researchService = null): void
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

            if ($researchService) {
                $run->update(['current_step' => 'research']);
                try {
                    $discovery = $researchService->discoverAndSyncFixtures($date);
                    $researchResults = $researchService->researchDate($date);
                    $summary['research_discovery'] = $discovery;
                    $summary['research_results'] = $researchResults;
                    $summary['research_count'] = count($researchResults);
                    $summary['research_mode'] = 'openrouter';
                    $summary['fixtures_eligible'] = count($discovery['synced'] ?? []);
                    $summary['fixtures_found'] = count($discovery['synced'] ?? []);
                    $run->update(['summary' => $summary]);

                    if (($summary['fixtures_found'] ?? 0) === 0) {
                        $summary['notice'] = 'Nenhum jogo foi encontrado para esta data.';
                        $run->update([
                            'status' => 'COMPLETED',
                            'finished_at' => now(),
                            'current_step' => 'done',
                            'summary' => $summary,
                        ]);

                        return;
                    }
                } catch (Exception $e) {
                    $summary['research_mode'] = 'fallback';
                    $summary['research_error'] = $e->getMessage();
                    $run->update(['summary' => $summary]);
                }
            }

            // Step 1: Sync Fixtures
            if ($run->fixtures_status !== 'COMPLETED' && ! $researchService) {
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

                if (($summary['fixtures_found'] ?? 0) === 0) {
                    $summary['notice'] = 'Nenhum jogo foi encontrado para esta data no plano atual da API.';

                    $run->update([
                        'status' => 'COMPLETED',
                        'finished_at' => now(),
                        'current_step' => 'done',
                        'summary' => $summary,
                    ]);

                    return;
                }
            }

            // Step 2: Build Datasets
            if ($run->datasets_status !== 'COMPLETED') {
                $run->update(['current_step' => 'datasets']);
                $exitCode = Artisan::call('fas:build-dataset', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:build-dataset failed with exit code {$exitCode}");
                }

                $fixtureIds = Fixture::whereDate('fixture_date', $date)->pluck('id');
                $summary['datasets_generated'] = MatchDatasetRecord::whereIn('fixture_id', $fixtureIds)
                    ->when($run->started_at, function ($query) use ($run) {
                        $query->whereBetween('generated_at', [$run->started_at, now()]);
                    })
                    ->count();

                $run->update(['datasets_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 3: Run Analysis
            if ($run->analysis_status !== 'COMPLETED') {
                $run->update(['current_step' => 'analysis']);
                $exitCode = Artisan::call('fas:analyze', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:analyze failed with exit code {$exitCode}");
                }

                $fixtureIds = Fixture::whereDate('fixture_date', $date)->pluck('id');
                $summary['analyses_generated'] = FasAnalysis::whereIn('fixture_id', $fixtureIds)
                    ->when($run->started_at, function ($query) use ($run) {
                        $query->whereBetween('created_at', [$run->started_at, now()]);
                    })
                    ->count();

                $run->update(['analysis_status' => 'COMPLETED', 'summary' => $summary]);
            }

            // Step 4: Generate Ranking
            if ($run->ranking_status !== 'COMPLETED') {
                $run->update(['current_step' => 'ranking']);
                $exitCode = Artisan::call('fas:rank', ['date' => $date]);

                if ($exitCode !== 0) {
                    throw new Exception("fas:rank failed with exit code {$exitCode}");
                }

                $rankingRun = FasRankingRun::whereDate('analysis_date', $date)->latest('generated_at')->first();
                if ($rankingRun) {
                    $summary['top3_count'] = $rankingRun->rankings()->where('ranking_type', 'TOP3')->count();
                    $summary['top5_count'] = $rankingRun->rankings()->where('ranking_type', 'TOP5')->count();
                    $summary['watchlist_count'] = $rankingRun->rankings()->where('ranking_type', 'WATCHLIST')->count();
                }

                $run->update(['ranking_status' => 'COMPLETED', 'summary' => $summary]);
            }

            $rankingRun = FasRankingRun::with([
                'rankings.event.analysis.fixture.homeTeam',
                'rankings.event.analysis.fixture.awayTeam',
            ])
                ->whereDate('analysis_date', $date)
                ->latest('generated_at')
                ->first();

            if ($rankingRun) {
                $summary['selected_games'] = [
                    'top3' => $this->serializeRankings($rankingRun, 'TOP3'),
                    'top5' => $this->serializeRankings($rankingRun, 'TOP5'),
                    'watchlist' => $this->serializeRankings($rankingRun, 'WATCHLIST'),
                ];

                $run->update(['summary' => $summary]);
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
            ->where('updated_at', '>=', now()->subMinute())
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

    private function serializeRankings(FasRankingRun $rankingRun, string $type): array
    {
        return $rankingRun->rankings
            ->where('ranking_type', $type)
            ->map(function ($ranking) {
                $fixture = $ranking->event?->analysis?->fixture;

                return [
                    'ranking_id' => $ranking->id,
                    'fixture_id' => $fixture?->id,
                    'home_team' => $fixture?->homeTeam?->name,
                    'away_team' => $fixture?->awayTeam?->name,
                    'competition' => $fixture?->competition?->name,
                    'candidate_score' => $ranking->candidate_score,
                    'watchlist_reason' => $ranking->watchlist_reason,
                ];
            })
            ->values()
            ->all();
    }
}
