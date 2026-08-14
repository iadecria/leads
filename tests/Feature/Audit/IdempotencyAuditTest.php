<?php

namespace Tests\Feature\Audit;

use App\Enums\FasAuditStatus;
use App\Enums\FasEventType;
use App\Models\FasAnalysis;
use App\Models\FasAudit;
use App\Models\FasEvent;
use App\Models\FasRanking;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use App\Services\Audit\FasAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_run_is_idempotent()
    {
        $run = FasRankingRun::factory()->create();
        $fixture = Fixture::factory()->create(['status' => 'FT', 'home_score' => 2, 'away_score' => 1]);
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);
        $event = FasEvent::factory()->create(['fas_analysis_id' => $analysis->id, 'event_type' => FasEventType::OVER_1_5, 'line' => 1.5]);
        $ranking = FasRanking::factory()->create(['fas_ranking_run_id' => $run->id, 'fas_event_id' => $event->id]);

        $service = new FasAuditService;

        // First Run
        $service->auditRun($run);

        $this->assertDatabaseCount('fas_audits', 1);
        $this->assertDatabaseHas('fas_audits', [
            'fas_ranking_run_id' => $run->id,
            'status' => FasAuditStatus::HIT->value,
            'is_correct' => true,
        ]);

        $firstAudit = FasAudit::first();
        $firstValidatedAt = $firstAudit->validated_at;

        // Second Run (should skip since it's HIT and force=false)
        sleep(1); // Ensure time passes
        $service->auditRun($run);

        $this->assertDatabaseCount('fas_audits', 1);
        $this->assertEquals($firstValidatedAt, FasAudit::first()->validated_at);

        // Third Run with force=true
        $service->auditRun($run, true);
        $this->assertDatabaseCount('fas_audits', 1); // Still 1, just updated
        $this->assertNotEquals($firstValidatedAt, FasAudit::first()->validated_at);
    }
}
