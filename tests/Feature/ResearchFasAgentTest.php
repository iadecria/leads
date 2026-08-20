<?php

namespace Tests\Feature;

use App\Models\FasExecutionRun;
use App\Models\ResearchFasRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchFasAgentTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAgentResponse(array $games = [], array $top3 = [], array $top5 = []): void
    {
        $result = [
            'games' => $games ?: [
                [
                    'fixture_id' => 1,
                    'home' => 'São Paulo',
                    'away' => 'Bolívar',
                    'competition' => 'Sul-Americana',
                    'kickoff' => '19:00',
                    'quality' => 'HIGH',
                    'events' => [
                        ['event_type' => 'OVER_1_5', 'label' => 'Over 1.5 gols', 'estimated_probability' => 0.78, 'confidence' => 'HIGH', 'reason' => 'Ambos atacam muito', 'sources' => ['https://a.com']],
                        ['event_type' => 'TEAM_TO_SCORE_1_PLUS', 'label' => 'São Paulo 1+', 'estimated_probability' => 0.72, 'confidence' => 'HIGH', 'reason' => 'Manda bem em casa', 'sources' => ['https://b.com']],
                        ['event_type' => 'FIRST_HALF_GOAL', 'label' => 'Gol HT', 'estimated_probability' => 0.67, 'confidence' => 'MEDIUM', 'reason' => 'Jogos recentes com gol cedo', 'sources' => ['https://c.com']],
                        ['event_type' => 'BTTS', 'label' => 'BTTS', 'estimated_probability' => 0.65, 'confidence' => 'MEDIUM', 'reason' => 'Defesas frágeis', 'sources' => ['https://d.com']],
                    ],
                    'sources' => ['https://a.com', 'https://b.com', 'https://c.com', 'https://d.com'],
                ],
            ],
            'top3' => $top3 ?: [
                ['fixture_id' => 1, 'event_type' => 'OVER_1_5', 'estimated_probability' => 0.78, 'confidence' => 'HIGH', 'reason' => 'Ambos atacam muito'],
            ],
            'top5' => $top5 ?: [
                ['fixture_id' => 1, 'event_type' => 'OVER_1_5', 'estimated_probability' => 0.78, 'confidence' => 'HIGH', 'reason' => 'Ambos atacam muito'],
                ['fixture_id' => 2, 'event_type' => 'BTTS', 'estimated_probability' => 0.70, 'confidence' => 'MEDIUM', 'reason' => 'Jogo equilibrado'],
            ],
            'best_games' => [
                ['fixture_id' => 1, 'events' => []],
            ],
            'ranking' => [],
        ];

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($result),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 1000,
                    'completion_tokens' => 2000,
                    'total_tokens' => 3000,
                ],
            ], 200),
        ]);
    }

    private function discoveredFixtures(): array
    {
        $futureTime = now()->setTimezone('America/Sao_Paulo')->addHours(3)->format('H:i');

        return [
            'window_1' => [
                [
                    'competition' => 'Brasileirão Série A',
                    'home_team' => 'São Paulo',
                    'away_team' => 'Bolívar',
                    'kickoff' => now()->addDays(2)->format('Y-m-d').' '.$futureTime,
                    'kickoff_time' => $futureTime,
                    'window' => 1,
                ],
                [
                    'competition' => 'Sul-Americana',
                    'home_team' => 'Fluminense',
                    'away_team' => 'América de Cali',
                    'kickoff' => now()->addDays(2)->format('Y-m-d').' '.$futureTime,
                    'kickoff_time' => $futureTime,
                    'window' => 1,
                ],
            ],
            'window_2' => [],
            'selected_count' => 2,
            'fixtures_eligible' => 2,
        ];
    }

    private function createDiscoveryRun(string $date): FasExecutionRun
    {
        return FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'summary' => $this->discoveredFixtures(),
        ]);
    }

    public function test_research_agent_analyzes_discovered_fixtures(): void
    {
        $this->fakeAgentResponse();

        $date = now()->addDays(2)->format('Y-m-d');
        $this->createDiscoveryRun($date);

        $response = $this->postJson('/fas/research/run', ['date' => $date]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'run_id', 'status', 'result', 'debug']);
        $response->assertJsonPath('result.top3.0.event_type', 'OVER_1_5');
        $response->assertJsonPath('result.best_games.0.home', 'São Paulo');
        $response->assertJsonPath('debug.fixtures_analyzed', 1);

        $this->assertDatabaseHas('research_fas_runs', [
            'status' => 'COMPLETED',
        ]);
    }

    public function test_started_fixtures_are_excluded(): void
    {
        $this->fakeAgentResponse();

        $pastTime = now()->setTimezone('America/Sao_Paulo')->subHours(2)->format('H:i');
        $futureTime = now()->setTimezone('America/Sao_Paulo')->addHours(3)->format('H:i');
        $date = now()->format('Y-m-d');

        FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'summary' => [
                'window_1' => [
                    [
                        'competition' => 'Brasileirão',
                        'home_team' => 'Time Iniciado',
                        'away_team' => 'Outro',
                        'kickoff' => $date.' '.$pastTime,
                        'kickoff_time' => $pastTime,
                        'window' => 1,
                    ],
                ],
                'window_2' => [],
                'selected_count' => 1,
            ],
        ]);

        $response = $this->postJson('/fas/research/run', ['date' => $date]);

        $response->assertStatus(200);
        $response->assertJsonPath('debug.fixtures_input', 0);
        $response->assertJsonPath('debug.fixtures_analyzed', 0);
    }

    public function test_one_bad_fixture_does_not_break_others(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'games' => [
                                    [
                                        'fixture_id' => 1,
                                        'home' => 'São Paulo',
                                        'away' => 'Bolívar',
                                        'competition' => 'Sul-Americana',
                                        'kickoff' => '19:00',
                                        'quality' => 'HIGH',
                                        'events' => [
                                            ['event_type' => 'OVER_1_5', 'label' => 'O1.5', 'estimated_probability' => 0.78, 'confidence' => 'HIGH', 'reason' => 'ok'],
                                        ],
                                        'sources' => ['https://a.com'],
                                    ],
                                    // Jogo ruim sem eventos — deve ser ignorado
                                    [
                                        'fixture_id' => 2,
                                        'home' => 'Time Ruim',
                                        'away' => 'Outro',
                                        'competition' => 'X',
                                        'kickoff' => '20:00',
                                        'quality' => 'LOW',
                                        'events' => [],
                                        'sources' => [],
                                    ],
                                ],
                                'top3' => [],
                                'top5' => [],
                                'best_games' => [],
                                'ranking' => [],
                            ]),
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 1000,
                    'completion_tokens' => 2000,
                    'total_tokens' => 3000,
                ],
            ], 200),
        ]);

        $date = now()->addDays(1)->format('Y-m-d');
        $this->createDiscoveryRun($date);

        $response = $this->postJson('/fas/research/run', ['date' => $date]);

        $response->assertStatus(200);
        // O jogo bom (fixture 1) é analisado; o jogo ruim (fixture 2 sem eventos) é ignorado
        $this->assertCount(1, $response->json('result.games'));
        $response->assertJsonPath('result.games.0.home', 'São Paulo');
        $response->assertJsonPath('debug.fixtures_analyzed', 1);
    }

    public function test_requires_discovery_first(): void
    {
        $date = now()->addDays(1)->format('Y-m-d');

        $response = $this->postJson('/fas/research/run', ['date' => $date]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Nenhum jogo descoberto para esta data. Execute BUSCAR JOGOS DO DIA primeiro.']);
    }

    public function test_snapshot_is_persisted(): void
    {
        $this->fakeAgentResponse();

        $date = now()->addDays(2)->format('Y-m-d');
        $this->createDiscoveryRun($date);

        $response = $this->postJson('/fas/research/run', ['date' => $date]);

        $response->assertStatus(200);

        $run = ResearchFasRun::first();
        $this->assertNotNull($run);
        $this->assertSame('COMPLETED', $run->status);
        $this->assertNotEmpty($run->result);
        $this->assertNotEmpty($run->debug);
        $this->assertSame('RESEARCH_AGENT', $run->result['metadata']['probability_source']);
        $this->assertSame('UNCALIBRATED', $run->result['metadata']['calibration_status']);
    }

    public function test_openrouter_error_returns_500_without_crash(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([], 500),
        ]);

        $date = now()->addDays(1)->format('Y-m-d');
        $this->createDiscoveryRun($date);

        $response = $this->postJson('/fas/research/run', ['date' => $date]);

        $response->assertStatus(500);
        $response->assertJsonStructure(['error']);
    }

    public function test_dashboard_renders_research_results(): void
    {
        $date = now()->format('Y-m-d');

        ResearchFasRun::create([
            'analysis_date' => $date,
            'status' => 'COMPLETED',
            'model' => 'test-model',
            'prompt_version' => '1.0.0',
            'generated_at' => now(),
            'result' => [
                'games' => [
                    [
                        'fixture_id' => 1,
                        'home' => 'São Paulo',
                        'away' => 'Bolívar',
                        'competition' => 'Sul-Americana',
                        'kickoff' => '19:00',
                        'events' => [
                            ['event_type' => 'OVER_1_5', 'label' => 'O1.5', 'estimated_probability' => 0.78, 'confidence' => 'HIGH'],
                        ],
                    ],
                ],
                'top3' => [
                    ['fixture_id' => 1, 'event_type' => 'OVER_1_5', 'label' => 'O1.5', 'estimated_probability' => 0.78, 'confidence' => 'HIGH'],
                ],
                'top5' => [],
                'best_games' => [],
                'ranking' => [],
                'metadata' => [],
            ],
            'debug' => [
                'fixtures_input' => 1,
                'fixtures_analyzed' => 1,
                'fixtures_rejected' => 0,
                'openrouter_calls' => 1,
                'tokens' => 3000,
                'cost_usd' => 0.001,
                'top3_count' => 1,
                'top5_count' => 0,
                'best_games_count' => 0,
            ],
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Top 3 FAS — Research Agent');
        // Os nomes são renderizados via Alpine x-text, verificar que os dados estão no JSON injetado
        $response->assertSee('researchResult');
    }
}
