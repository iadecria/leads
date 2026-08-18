<?php

namespace App\Console\Commands;

use App\Enums\RankingType;
use App\Models\FasAnalysis;
use App\Models\FasExecutionRun;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use App\Models\MatchDatasetRecord;
use App\Services\Fas\Engines\FasRankingEngine;
use App\Services\Research\ResearchDatasetAdapter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use ReflectionMethod;

class FasDiagnoseCommand extends Command
{
    protected $signature = 'fas:diagnose {date}';

    protected $description = 'Diagnose the FAS pipeline for a given date without mutating data.';

    public function handle(FasRankingEngine $rankingEngine, ResearchDatasetAdapter $researchDatasetAdapter): int
    {
        $date = Carbon::parse($this->argument('date'));
        $dateStr = $date->toDateString();

        $run = FasExecutionRun::where('execution_type', 'DAILY_ANALYSIS')
            ->whereDate('analysis_date', $dateStr)
            ->latest('id')
            ->first();

        $rankingRun = FasRankingRun::whereDate('analysis_date', $dateStr)
            ->latest('generated_at')
            ->first();

        $this->line("=== FAS DIAGNOSE {$dateStr} ===");
        $this->line('Latest run: '.($run?->id ?? 'none'));
        $this->line('Ranking run: '.($rankingRun?->id ?? 'none'));
        $this->newLine();

        $fixtures = Fixture::with(['competition', 'homeTeam', 'awayTeam', 'statistics', 'events', 'analyses.events'])
            ->whereDate('fixture_date', $dateStr)
            ->orderBy('fixture_date')
            ->get();

        $fixtureIds = $fixtures->pluck('id');
        $datasetsGenerated = MatchDatasetRecord::whereIn('fixture_id', $fixtureIds)->count();
        $analysesGenerated = FasAnalysis::whereIn('fixture_id', $fixtureIds)->count();

        $this->line('=== FUNNEL ===');
        $this->line('Fixtures discovered: '.$fixtures->count());
        $this->line('Fixtures eligible: '.$fixtures->where('fas_status', 'ELIGIBLE')->count());
        $this->line('Research successful: '.($run?->summary['research_mode'] === 'openrouter' ? count($run->summary['research_results'] ?? []) : 0));
        $this->line('Research fallback: '.($run?->summary['research_mode'] === 'fallback' ? 1 : 0));
        $this->line('Datasets generated: '.$datasetsGenerated);
        $this->line('Datasets insufficient: '.$fixtures->filter(function ($fixture) {
            return MatchDatasetRecord::where('fixture_id', $fixture->id)->latest('generated_at')->first()?->data_quality_level === 'INSUFFICIENT';
        })->count());
        $this->line('Analyses generated: '.$analysesGenerated);
        $this->line('Events generated: '.($rankingRun?->rankings()->count() ?? 0));
        $this->line('Events with sufficient data: '.($rankingRun?->rankings()->where('candidate_score', '>', 0)->count() ?? 0));
        $this->line('Official candidates: '.($rankingRun?->rankings()->whereIn('ranking_type', [RankingType::TOP3->value, RankingType::TOP5->value])->count() ?? 0));
        $this->line('Watchlist candidates: '.($rankingRun?->rankings()->where('ranking_type', RankingType::WATCHLIST->value)->count() ?? 0));
        $this->line('TOP3: '.($rankingRun?->rankings()->where('ranking_type', RankingType::TOP3->value)->count() ?? 0));
        $this->line('TOP5: '.($rankingRun?->rankings()->where('ranking_type', RankingType::TOP5->value)->count() ?? 0));
        $this->newLine();

        foreach ($fixtures as $fixture) {
            $dataset = MatchDatasetRecord::where('fixture_id', $fixture->id)->latest('generated_at')->first();
            $analysis = FasAnalysis::with('events')->where('fixture_id', $fixture->id)->latest('created_at')->first();

            $this->line('=== FIXTURE ===');
            $this->line(($fixture->homeTeam?->name ?? 'Home').' x '.($fixture->awayTeam?->name ?? 'Away'));
            $this->line('Competition: '.($fixture->competition?->name ?? '-'));
            $this->line('Kickoff: '.optional($fixture->fixture_date)->format('H:i'));
            $this->line('Dataset quality: '.($dataset?->data_quality_score ?? 0).' '.($dataset?->data_quality_level ?? 'NONE'));
            $this->line('Analysis id: '.($analysis?->id ?? 'none'));
            $this->line('Event predictions: '.($analysis?->events->count() ?? 0));

            $researchResult = collect($run?->summary['research_results'] ?? [])->firstWhere('fixture_id', $fixture->id)['data'] ?? null;
            if ($researchResult) {
                $debugCounts = $researchDatasetAdapter->buildDebugCounts(
                    $researchResult,
                    $fixture->homeTeam?->name ?? '',
                    $fixture->awayTeam?->name ?? '',
                    $fixture->fixture_date->toIso8601String()
                );

                $this->line('Research matches home: '.$debugCounts['research_matches_home']);
                $this->line('Normalized home: '.$debugCounts['normalized_home']);
                $this->line('Dataset home last5: '.$debugCounts['dataset_home_last5']);
                $this->line('Dataset home last10: '.$debugCounts['dataset_home_last10']);
                $this->line('Research matches away: '.$debugCounts['research_matches_away']);
                $this->line('Normalized away: '.$debugCounts['normalized_away']);
                $this->line('Dataset away last5: '.$debugCounts['dataset_away_last5']);
                $this->line('Dataset away last10: '.$debugCounts['dataset_away_last10']);
            }

            if ($analysis) {
                foreach ($analysis->events as $event) {
                    $payload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true);
                    $eventType = $event->event_type->value;
                    $evaluation = $this->evaluateEvent($rankingEngine, $event, $fixture->fixture_date->toDateString());

                    $this->line(" - {$eventType} @ ".($event->line ?? '-'));
                    $this->line('   adjusted: '.number_format((float) ($payload['adjusted_probability'] ?? 0) * 100, 1).'%');
                    $this->line('   fas_score: '.($event->fas_score ?? 0));
                    $this->line('   confidence: '.($event->confidence->value ?? (string) $event->confidence));
                    $this->line('   ranking: '.$evaluation['status']);
                    $this->line('   reason: '.($evaluation['watchlist_reason'] ?? 'OK'));
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function evaluateEvent(FasRankingEngine $rankingEngine, $event, string $date): array
    {
        $method = new ReflectionMethod($rankingEngine, 'evaluateEvent');
        $method->setAccessible(true);

        return $method->invoke($rankingEngine, $event);
    }
}
