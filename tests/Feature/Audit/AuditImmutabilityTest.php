<?php

namespace Tests\Feature\Audit;

use App\Enums\FasEventType;
use App\Models\FasAnalysis;
use App\Models\FasAudit;
use App\Models\FasEvent;
use App\Models\FasRanking;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use App\Services\Audit\FasAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_preserves_engine_versions()
    {
        Config::set('fas.engine_version', '1.5.0');
        Config::set('fas.dataset_version', '1.2.0');
        Config::set('fas.audit.version', '2.0.0');

        $run = FasRankingRun::factory()->create(['engine_version' => '1.0.0']);
        $fixture = Fixture::factory()->create(['status' => 'FT', 'home_score' => 2, 'away_score' => 1]);
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);
        $event = FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_1_5,
            'line' => 1.5,
            'estimated_probability' => 0.85,
        ]);
        $ranking = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event->id]);

        $service = new FasAuditService;
        $service->auditRun($run);

        $audit = FasAudit::first();

        // The versions at the time of audit
        $this->assertEquals('1.5.0', $audit->engine_version);
        $this->assertEquals('1.2.0', $audit->dataset_version);
        $this->assertEquals('2.0.0', $audit->audit_version);

        // The original ranking version
        $this->assertEquals('1.0.0', $audit->ranking_version);

        // Payload check
        $this->assertEquals(0.85, $audit->payload['predicted_probability']);
        $this->assertEquals('OVER_1_5', $audit->payload['event']);
        $this->assertEquals(1.5, $audit->payload['line']);
    }
}
