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

class PerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_overall_metrics()
    {
        $run = FasRankingRun::factory()->create();
        $fixture = Fixture::factory()->create();
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);
        $event = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id]);
        $ranking = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event->id]);

        FasAudit::create([
            'fas_ranking_run_id' => $run->id,
            'fas_ranking_id' => $ranking->id,
            'fas_event_id' => $event->id,
            'fixture_id' => $fixture->id,
            'status' => 'HIT',
            'audit_version' => '1.0.0',
            'ranking_version' => '1.0.0',
            'engine_version' => '1.0.0',
            'dataset_version' => '1.0.0',
        ]);

        $service = new FasPerformanceService;
        $metrics = $service->getOverallMetrics();

        $this->assertEquals(1, $metrics['total_predictions']);
        $this->assertEquals(1, $metrics['hits']);
        $this->assertEquals(100.0, $metrics['hit_rate']);
    }

    public function test_get_tier_metrics_top5()
    {
        $run = FasRankingRun::factory()->create();
        $fixture = Fixture::factory()->create();
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);

        // TOP 3 Pos 1 (Hit)
        $event1 = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id]);
        $ranking1 = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event1->id, 'ranking_type' => 'TOP3', 'position' => 1]);
        FasAudit::create([
            'fas_ranking_run_id' => $run->id, 'fas_ranking_id' => $ranking1->id, 'fas_event_id' => $event1->id, 'fixture_id' => $fixture->id,
            'status' => 'HIT', 'audit_version' => '1.0.0', 'ranking_version' => '1.0.0', 'engine_version' => '1.0.0', 'dataset_version' => '1.0.0',
        ]);

        // TOP 5 Pos 4 (Hit)
        $event2 = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id]);
        $ranking2 = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event2->id, 'ranking_type' => 'TOP5', 'position' => 4]);
        FasAudit::create([
            'fas_ranking_run_id' => $run->id, 'fas_ranking_id' => $ranking2->id, 'fas_event_id' => $event2->id, 'fixture_id' => $fixture->id,
            'status' => 'HIT', 'audit_version' => '1.0.0', 'ranking_version' => '1.0.0', 'engine_version' => '1.0.0', 'dataset_version' => '1.0.0',
        ]);

        // WATCHLIST Pos 6 (Miss)
        $event3 = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id]);
        $ranking3 = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event3->id, 'ranking_type' => 'WATCHLIST', 'position' => 6]);
        FasAudit::create([
            'fas_ranking_run_id' => $run->id, 'fas_ranking_id' => $ranking3->id, 'fas_event_id' => $event3->id, 'fixture_id' => $fixture->id,
            'status' => 'MISS', 'audit_version' => '1.0.0', 'ranking_version' => '1.0.0', 'engine_version' => '1.0.0', 'dataset_version' => '1.0.0',
        ]);

        $service = new FasPerformanceService;
        $tiers = $service->getTierMetrics();

        // TOP 3 = 1 Hit
        $this->assertEquals(1, $tiers['top3']['hits']);
        $this->assertEquals(0, $tiers['top3']['misses']);

        // TOP 5 = Both TOP3 Pos 1 and TOP5 Pos 4 should be aggregated in top5 as they are <= 5
        $this->assertEquals(2, $tiers['top5']['hits']);
        $this->assertEquals(0, $tiers['top5']['misses']);

        // WATCHLIST = 1 Miss
        $this->assertEquals(0, $tiers['watchlist']['hits']);
        $this->assertEquals(1, $tiers['watchlist']['misses']);
    }
}
