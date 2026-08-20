<?php

namespace Tests\Feature;

use App\Models\FasExecutionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameDayDiscoveryEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAllBlocks(array $fixtures): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push($this->blockResponse($fixtures), 200)
                ->push($this->blockResponse($fixtures), 200)
                ->push($this->blockResponse($fixtures), 200),
        ]);
    }

    private function blockResponse(array $fixtures): array
    {
        return [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'fixtures' => $fixtures,
                            'sources' => ['https://example.com/source'],
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 800,
                'completion_tokens' => 1200,
                'total_tokens' => 2000,
            ],
        ];
    }

    private function plFixture(string $home, string $away, string $kickoff = '14:30'): array
    {
        return [
            'competition' => 'Premier League',
            'country' => 'England',
            'home_team' => $home,
            'away_team' => $away,
            'kickoff' => $kickoff,
        ];
    }

    public function test_search_endpoint_persists_discovery_run(): void
    {
        $this->fakeAllBlocks([$this->plFixture('Arsenal', 'Chelsea')]);

        $date = now()->addDays(1)->format('Y-m-d');

        $response = $this->postJson('/gameday/search', ['date' => $date]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'run_id', 'result']);
        $response->assertJson(['message' => 'Busca de jogos do dia concluída.']);
        $response->assertJsonPath('result.fixtures_eligible', 1);
        $response->assertJsonPath('result.window_1.0.home_team', 'Arsenal');
        $response->assertJsonPath('result.calls', 3);

        $run = FasExecutionRun::where('execution_type', 'GAMEDAY_DISCOVERY')
            ->whereDate('analysis_date', $date)
            ->first();

        $this->assertNotNull($run);
        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame(1, $run->summary['selected_count']);
    }

    public function test_search_endpoint_blocks_past_dates(): void
    {
        $response = $this->postJson('/gameday/search', ['date' => now()->subDay()->format('Y-m-d')]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'A busca de jogos só pode ser feita para hoje ou datas futuras.']);
    }

    public function test_search_endpoint_blocks_concurrent_runs(): void
    {
        $date = now()->addDays(1)->format('Y-m-d');

        FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'RUNNING',
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/gameday/search', ['date' => $date]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Já existe uma busca em andamento para esta data.']);
    }

    public function test_latest_endpoint_returns_discovered_games(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'summary' => [
                'date' => $date,
                'selected_count' => 1,
                'window_1' => [
                    [
                        'home_team' => 'Arsenal',
                        'away_team' => 'Chelsea',
                    ],
                ],
                'window_2' => [],
            ],
        ]);

        $response = $this->getJson('/gameday/latest?date='.$date);

        $response->assertStatus(200);
        $response->assertJsonPath('result.selected_count', 1);
        $response->assertJsonPath('result.window_1.0.home_team', 'Arsenal');
    }

    public function test_dashboard_renders_discovered_games_section(): void
    {
        $this->fakeAllBlocks([]);

        FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => now()->toDateString(),
            'status' => 'COMPLETED',
            'summary' => [
                'date' => now()->toDateString(),
                'selected_count' => 1,
                'window_1' => [
                    $this->plFixture('Arsenal', 'Chelsea'),
                ],
                'window_2' => [],
            ],
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('BUSCAR JOGOS DO DIA');
        $response->assertSee('RODAR FAS');
        $response->assertSee('JOGOS ATÉ 17H (Janela 1)');
        $response->assertSee('JOGOS APÓS 17H (Janela 2)');
    }

    public function test_empty_discovery_does_not_break_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('BUSCAR JOGOS DO DIA');
        $response->assertSee('RODAR FAS');
    }

    public function test_discovery_with_fixtures_renders_games_and_enables_fas(): void
    {
        $this->fakeAllBlocks([$this->plFixture('Arsenal', 'Chelsea')]);

        $date = now()->addDays(1)->format('Y-m-d');

        // Discovery persiste
        $this->postJson('/gameday/search', ['date' => $date])->assertStatus(200);

        // Dashboard carrega os fixtures persistidos
        $response = $this->get('/dashboard?date='.$date);

        $response->assertStatus(200);
        $response->assertSee('Jogos do Dia');
        $response->assertSee('JOGOS ATÉ 17H (Janela 1)');
        $response->assertSee('JOGOS APÓS 17H (Janela 2)');
        // Os nomes dos times estão no JSON injetado no Alpine
        $response->assertSee('Arsenal');
        $response->assertSee('Chelsea');
        // Contadores da descoberta
        $response->assertSee('Europa:');
        $response->assertSee('Eligible:');
        $response->assertSee('RODAR FAS');
        // hasDiscoveredGames = fixtures_eligible > 0, então RODAR FAS habilitado
        $response->assertSee('disabled:cursor-not-allowed disabled:opacity-50');
    }

    public function test_empty_discovery_ephemeral_shows_empty_message(): void
    {
        $this->fakeAllBlocks([]);

        $date = now()->addDays(1)->format('Y-m-d');

        $this->postJson('/gameday/search', ['date' => $date])->assertStatus(200);

        // Dashboard mostra estado EMPTY
        $response = $this->get('/dashboard?date='.$date);

        $response->assertStatus(200);
        $response->assertSee('Jogos do Dia');
        $response->assertSee('JOGOS ATÉ 17H (Janela 1)');
        $response->assertSee('JOGOS APÓS 17H (Janela 2)');
        $response->assertSee('Sem jogos elegíveis nesta janela.');
        $response->assertSee('DISCOVERY_EMPTY');
    }

    public function test_dashboard_prefers_last_success_over_later_empty(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        // Run A — SUCCESS com 5 fixtures (mais antigo)
        $successRun = FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'summary' => [
                'date' => $date,
                'discovery_status' => 'DISCOVERY_SUCCESS',
                'discovery_europa' => 5,
                'discovery_brasil' => 0,
                'discovery_americas' => 0,
                'fixtures_eligible' => 5,
                'selected_count' => 5,
                'window_1' => [
                    $this->plFixture('Arsenal', 'Chelsea'),
                    $this->plFixture('Benfica', 'AGF', '16:00'),
                ],
                'window_2' => [],
                'calls' => 3,
                'tokens' => 10000,
                'estimated_cost_usd' => 0.005,
            ],
        ]);
        $successRun->update(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);

        // Run B — EMPTY mais recente (não deve esconder Run A)
        $emptyRun = FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'summary' => [
                'date' => $date,
                'discovery_status' => 'DISCOVERY_EMPTY',
                'discovery_europa' => 0,
                'discovery_brasil' => 0,
                'discovery_americas' => 0,
                'fixtures_eligible' => 0,
                'selected_count' => 0,
                'window_1' => [],
                'window_2' => [],
                'calls' => 3,
                'tokens' => 5000,
                'estimated_cost_usd' => 0.002,
            ],
        ]);
        $emptyRun->update(['created_at' => now(), 'updated_at' => now()]);

        $response = $this->get('/dashboard?date='.$date);

        $response->assertStatus(200);
        $response->assertSee('Arsenal');
        $response->assertSee('Benfica');
        $response->assertSee('DISCOVERY_SUCCESS');
        $response->assertSee('RODAR FAS');
        $response->assertDontSee('DISCOVERY_EMPTY');
    }

    public function test_dashboard_shows_empty_when_no_success_run_exists(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'summary' => [
                'date' => $date,
                'discovery_status' => 'DISCOVERY_EMPTY',
                'discovery_europa' => 0,
                'discovery_brasil' => 0,
                'discovery_americas' => 0,
                'fixtures_eligible' => 0,
                'selected_count' => 0,
                'window_1' => [],
                'window_2' => [],
                'calls' => 3,
                'tokens' => 5000,
                'estimated_cost_usd' => 0.002,
            ],
        ]);

        $response = $this->get('/dashboard?date='.$date);

        $response->assertStatus(200);
        $response->assertSee('Jogos do Dia');
        $response->assertSee('DISCOVERY_EMPTY');
        $response->assertSee('Sem jogos elegíveis nesta janela.');
        $response->assertSee('RODAR FAS');
    }

    public function test_discovery_fixtures_survive_empty_ranking(): void
    {
        $this->fakeAllBlocks([$this->plFixture('Flamengo', 'Gremio')]);

        $date = now()->addDays(2)->format('Y-m-d');

        // Discovery persiste (ranking vazio — sem FasRankingRun)
        $this->postJson('/gameday/search', ['date' => $date])->assertStatus(200);

        // Dashboard sem ranking mas com fixtures descobertos
        $response = $this->get('/dashboard?date='.$date);

        $response->assertStatus(200);
        $response->assertSee('Jogos do Dia');
        $response->assertSee('Flamengo');
        $response->assertSee('Gremio');
        $response->assertSee('RODAR FAS');
        // Sem TOP3 determinístico mas com fixtures — jogos continuam aparecendo
        $response->assertSee('Nenhum TOP 3 disponível para esta data.');
        $response->assertSee('Nenhum TOP 5 disponível para esta data.');
    }
}