<?php

namespace Tests\Feature\Performance;

use App\Models\FasAnalysis;
use App\Models\FasAudit;
use App\Models\FasEvent;
use App\Models\FasRanking;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use App\Services\Performance\FasPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricCalculationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_brier_score_and_calibration_exact_math()
    {
        $run = FasRankingRun::factory()->create();
        $fixture = Fixture::factory()->create();
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);

        // HIT: Prob = 0.80 -> Brier = (0.8 - 1)^2 = 0.04
        $eventHit = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id, 'estimated_probability' => 0.80]);
        $rankingHit = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $eventHit->id, 'ranking_type' => 'TOP3']);
        FasAudit::create([
            'fas_ranking_run_id' => $run->id,
            'fas_ranking_id' => $rankingHit->id,
            'fas_event_id' => $eventHit->id,
            'fixture_id' => $fixture->id,
            'status' => 'HIT',
            'audit_version' => '1.0.0',
            'ranking_version' => '1.0.0',
            'engine_version' => '1.0.0',
            'dataset_version' => '1.0.0',
        ]);

        // MISS: Prob = 0.80 -> Brier = (0.8 - 0)^2 = 0.64
        $eventMiss = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id, 'estimated_probability' => 0.80]);
        $rankingMiss = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $eventMiss->id, 'ranking_type' => 'TOP3']);
        FasAudit::create([
            'fas_ranking_run_id' => $run->id,
            'fas_ranking_id' => $rankingMiss->id,
            'fas_event_id' => $eventMiss->id,
            'fixture_id' => $fixture->id,
            'status' => 'MISS',
            'audit_version' => '1.0.0',
            'ranking_version' => '1.0.0',
            'engine_version' => '1.0.0',
            'dataset_version' => '1.0.0',
        ]);

        $service = new FasPerformanceService;
        $metrics = $service->getOverallMetrics();

        // Total Brier = (0.04 + 0.64) / 2 = 0.34
        $this->assertEquals(0.34, $metrics['brier_score']);

        // Calibration: Hit Rate (50%) - Avg Prob (80%) = -0.30
        $this->assertEquals(0.50, $metrics['hit_rate'] / 100);
        $this->assertEquals(-0.30, $metrics['calibration_gap']);
    }
}
