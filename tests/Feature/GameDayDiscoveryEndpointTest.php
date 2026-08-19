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
}