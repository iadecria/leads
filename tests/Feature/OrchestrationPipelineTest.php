<?php

namespace Tests\Feature;

use App\Models\FasExecutionRun;
use App\Services\Orchestration\FasDailyOrchestrator;
use App\Services\Orchestration\FasResultOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OrchestrationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_lock_prevents_duplicate_runs()
    {
        $date = now()->format('Y-m-d');

        FasExecutionRun::create([
            'execution_type' => 'DAILY_ANALYSIS',
            'analysis_date' => $date,
            'status' => 'RUNNING',
        ]);

        $response = $this->postJson('/fas/executions/daily', ['date' => $date]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Já existe uma execução em andamento para esta data.']);

        $this->assertEquals(1, FasExecutionRun::count());
    }

    public function test_audit_in_future_is_blocked()
    {
        $date = now()->addDays(2)->format('Y-m-d');

        $response = $this->postJson('/fas/executions/audit', ['date' => $date]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Não há resultados a conferir para uma data futura.']);
    }

    public function test_daily_orchestrator_updates_steps()
    {
        Artisan::shouldReceive('call')->with('fas:sync-fixtures', ['date' => '2026-08-14'])->once()->andReturn(0);
        Artisan::shouldReceive('call')->with('fas:build-dataset', ['date' => '2026-08-14'])->once()->andReturn(0);
        Artisan::shouldReceive('call')->with('fas:analyze', ['date' => '2026-08-14'])->once()->andReturn(0);
        Artisan::shouldReceive('call')->with('fas:rank', ['date' => '2026-08-14'])->once()->andReturn(0);

        Artisan::shouldReceive('output')->andReturnValues([
            "Elegíveis: 1\nEncontrados: 1",
            'Gerados: 1',
            'Total Analyzed: 1',
            "TOP 3: 1\nTOP 5: 1\nWatchlist: 1",
        ]);

        $run = FasExecutionRun::create([
            'execution_type' => 'DAILY_ANALYSIS',
            'analysis_date' => '2026-08-14',
            'status' => 'PENDING',
        ]);

        $orchestrator = new FasDailyOrchestrator;
        $orchestrator->execute($run);

        $run->refresh();

        $this->assertEquals('COMPLETED', $run->status);
        $this->assertEquals('COMPLETED', $run->fixtures_status);
        $this->assertEquals('COMPLETED', $run->datasets_status);
        $this->assertEquals('COMPLETED', $run->analysis_status);
        $this->assertEquals('COMPLETED', $run->ranking_status);
        $this->assertEquals(1, $run->summary['fixtures_eligible']);
    }

    public function test_daily_orchestrator_handles_partial_failure()
    {
        Artisan::shouldReceive('call')->with('fas:sync-fixtures', ['date' => '2026-08-14'])->once()->andReturn(0);
        Artisan::shouldReceive('output')->andReturn("Elegíveis: 1\nEncontrados: 1");

        // Mock failure on datasets
        Artisan::shouldReceive('call')->with('fas:build-dataset', ['date' => '2026-08-14'])->once()->andReturn(1);

        $run = FasExecutionRun::create([
            'execution_type' => 'DAILY_ANALYSIS',
            'analysis_date' => '2026-08-14',
            'status' => 'PENDING',
        ]);

        $orchestrator = new FasDailyOrchestrator;
        $orchestrator->execute($run);

        $run->refresh();

        $this->assertEquals('FAILED', $run->status);
        $this->assertEquals('COMPLETED', $run->fixtures_status);
        $this->assertEquals('FAILED', $run->datasets_status);
        $this->assertNull($run->analysis_status); // Did not reach
        $this->assertNotNull($run->errors);
        $this->assertStringContainsString('fas:build-dataset failed', $run->errors[0]['message']);
    }

    public function test_audit_orchestrator_flow()
    {
        $date = now()->subDay()->format('Y-m-d');
        Artisan::shouldReceive('call')->with('fas:sync-results', ['date' => $date])->once()->andReturn(0);
        Artisan::shouldReceive('call')->with('fas:audit', ['date' => $date])->once()->andReturn(0);
        Artisan::shouldReceive('output')->andReturnValues([
            'Resultados Sincronizados: 1',
            "HIT: 1\nMISS: 0",
        ]);

        $run = FasExecutionRun::create([
            'execution_type' => 'RESULT_AUDIT',
            'analysis_date' => $date,
            'status' => 'PENDING',
        ]);

        $orchestrator = new FasResultOrchestrator;
        $orchestrator->execute($run);

        $run->refresh();

        $this->assertEquals('COMPLETED', $run->status);
        $this->assertEquals('COMPLETED', $run->results_status);
        $this->assertEquals('COMPLETED', $run->audit_status);
        $this->assertEquals(1, $run->summary['audits_hit']);
    }
}
