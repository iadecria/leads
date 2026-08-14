<?php

namespace Tests\Feature\Audit;

use App\Models\FasRankingRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_audits_dashboard()
    {
        $run = FasRankingRun::factory()->create();

        $response = $this->get(route('audits.show', $run->id));

        $response->assertStatus(200);
        $response->assertSee('Auditoria de Ranking');
    }
}
