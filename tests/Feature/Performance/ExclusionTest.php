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

class ExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_void_and_unavailable_do_not_contaminate_hit_rate()
    {
        $run = FasRankingRun::factory()->create();
        $fixture = Fixture::factory()->create();
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);

        $statuses = ['HIT', 'MISS', 'VOID', 'UNAVAILABLE', 'PENDING'];

        foreach ($statuses as $status) {
            $event = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id]);
            $ranking = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event->id]);
            FasAudit::create([
                'fas_ranking_run_id' => $run->id,
                'fas_ranking_id' => $ranking->id,
                'fas_event_id' => $event->id,
                'fixture_id' => $fixture->id,
                'status' => $status,
                'audit_version' => '1.0.0',
                'ranking_version' => '1.0.0',
                'engine_version' => '1.0.0',
                'dataset_version' => '1.0.0',
            ]);
        }

        $service = new FasPerformanceService;
        $metrics = $service->getOverallMetrics();

        // 5 total, but only 2 audited (1 HIT, 1 MISS) -> Hit Rate 50%
        $this->assertEquals(5, $metrics['total_predictions']);
        $this->assertEquals(2, $metrics['audited_predictions']);
        $this->assertEquals(50.0, $metrics['hit_rate']);
        $this->assertEquals(1, $metrics['hits']);
        $this->assertEquals(1, $metrics['misses']);
        $this->assertEquals(1, $metrics['voids']);
        $this->assertEquals(1, $metrics['unavailable']);
        $this->assertEquals(1, $metrics['pending']);
    }
}
