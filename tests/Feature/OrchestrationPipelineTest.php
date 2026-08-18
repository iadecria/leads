<?php

namespace Tests\Feature;

use App\Jobs\RunFasDailyPipelineJob;
use App\Models\FasExecutionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OrchestrationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_lock_prevents_duplicate_runs(): void
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
    }

    public function test_daily_generation_for_past_date_is_blocked(): void
    {
        $response = $this->postJson('/fas/executions/daily', ['date' => now()->subDay()->format('Y-m-d')]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Análises pré-jogo só podem ser geradas para hoje ou datas futuras.']);
    }

    public function test_daily_generation_for_today_is_allowed(): void
    {
        Bus::fake();

        $response = $this->postJson('/fas/executions/daily', ['date' => now()->toDateString()]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'run_id']);
        Bus::assertDispatched(RunFasDailyPipelineJob::class);
    }

    public function test_audit_in_future_is_blocked(): void
    {
        $response = $this->postJson('/fas/executions/audit', ['date' => now()->addDays(2)->format('Y-m-d')]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Não há resultados a conferir para uma data futura.']);
    }

    public function test_audit_without_analysis_is_blocked(): void
    {
        $response = $this->postJson('/fas/executions/audit', ['date' => now()->subDay()->format('Y-m-d')]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Nenhuma análise foi gerada para esta data.']);
    }

}
