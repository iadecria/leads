<?php

namespace App\Services\OpenRouter;

use App\Models\ResearchFasRun;
use Carbon\Carbon;

class OpenRouterFasAnalysisService
{
    private array $allowedEvents = [
        'HOME_WIN', 'AWAY_WIN', 'DRAW',
        'OVER_1_5', 'OVER_2_5', 'FIRST_HALF_GOAL', 'BTTS',
        'OVER_CORNERS', 'OVER_CARDS', 'TEAM_TO_SCORE_1_PLUS',
    ];

    public function __construct(
        private OpenRouterClient $client
    ) {}

    public function analyze(string $date, ?int $window = null, array $discovered = []): array
    {
        $timezone = config('fas.discovery.timezone', 'America/Sao_Paulo');
        $cutoffAt = now()->setTimezone($timezone)->toIso8601String();

        // 1. Filtrar fixtures já iniciados
        $nowInTz = now()->setTimezone($timezone);
        $input = array_values(array_filter($discovered, function ($f) use ($nowInTz, $timezone) {
            $kickoff = Carbon::parse($f['kickoff'], $timezone);

            return $kickoff->greaterThan($nowInTz);
        }));

        // 2. Filtrar por janela se solicitado
        if ($window) {
            $input = array_values(array_filter($input, fn ($f) => ($f['window'] ?? null) == $window));
        }

        // 3. Limitar quantidade
        $max = (int) config('fas.discovery.research_agent.max_analyzed_fixtures', 20);
        $input = array_slice($input, 0, $max);

        if (empty($input)) {
            return $this->emptyResult($date, $window, 'Nenhum jogo elegível ainda não iniciado.');
        }

        // 4. Criar run snapshot
        $run = ResearchFasRun::create([
            'analysis_date' => $date,
            'window' => $window,
            'status' => 'RUNNING',
            'model' => config('fas.discovery.research_agent.model'),
            'prompt_version' => config('fas.discovery.research_agent.prompt_version', '1.0.0'),
            'generated_at' => now(),
            'input_fixtures' => $input,
        ]);

        try {
            // 5. Chamar openrouter agent
            $result = $this->callAgent($date, $input, $cutoffAt);

            $parsed = $result['result'] ?? [];
            $debug = $result['debug'] ?? [];

            // 6. Normalizar e validar resposta
            $normalized = $this->normalizeResult($parsed, $input);

            $debugOutput = [
                'fixtures_input' => count($input),
                'fixtures_analyzed' => count($normalized['games']),
                'fixtures_rejected' => count($input) - count($normalized['games']),
                'openrouter_calls' => $debug['web_search_requested'] ?? false ? 1 : 0,
                'web_search_executed' => (bool) ($debug['web_search_executed'] ?? false),
                'sources' => $debug['sources'] ?? [],
                'tokens' => (int) ($debug['usage']['total_tokens'] ?? 0),
                'prompt_tokens' => (int) ($debug['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($debug['usage']['completion_tokens'] ?? 0),
                'cost_usd' => $this->estimateCost($debug['usage'] ?? []),
                'top3_count' => count($normalized['top3']),
                'top5_count' => count($normalized['top5']),
                'best_games_count' => count($normalized['best_games']),
                'ranking_count' => count($normalized['ranking']),
            ];

            $run->update([
                'status' => 'COMPLETED',
                'result' => $normalized,
                'debug' => $debugOutput,
            ]);

            return [
                'run_id' => $run->id,
                'status' => 'COMPLETED',
                'result' => $normalized,
                'debug' => $debugOutput,
            ];
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'FAILED',
                'errors' => [[
                    'message' => $e->getMessage(),
                    'time' => now()->toDateTimeString(),
                ]],
            ]);

            throw $e;
        }
    }

    private function callAgent(string $date, array $fixtures, string $cutoffAt): array
    {
        $prompt = $this->buildAgentPrompt($date, $fixtures, $cutoffAt);

        return $this->client->researchWebPluginJsonWithDebug(
            $prompt,
            $this->requestOptions()
        );
    }

    private function requestOptions(): array
    {
        $eventEnum = ['HOME_WIN', 'AWAY_WIN', 'DRAW', 'OVER_1_5', 'OVER_2_5', 'FIRST_HALF_GOAL', 'BTTS', 'OVER_CORNERS', 'OVER_CARDS', 'TEAM_TO_SCORE_1_PLUS'];

        $schema = [
            'type' => 'object',
            'properties' => [
                'games' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'fixture_id' => ['type' => ['string', 'integer']],
                            'home' => ['type' => 'string'],
                            'away' => ['type' => 'string'],
                            'competition' => ['type' => 'string'],
                            'kickoff' => ['type' => 'string'],
                            'quality' => ['type' => 'string'],
                            'events' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'event_type' => ['type' => 'string', 'enum' => $eventEnum],
                                        'label' => ['type' => 'string'],
                                        'estimated_probability' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                                        'confidence' => ['type' => 'string'],
                                        'reason' => ['type' => 'string'],
                                        'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    ],
                                    'required' => ['event_type', 'label', 'estimated_probability', 'confidence'],
                                    'additionalProperties' => true,
                                ],
                            ],
                            'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['fixture_id', 'home', 'away', 'competition', 'kickoff', 'quality'],
                        'additionalProperties' => true,
                    ],
                ],
                'top3' => ['type' => 'array'],
                'top5' => ['type' => 'array'],
                'best_games' => ['type' => 'array'],
                'ranking' => ['type' => 'array'],
            ],
            'required' => ['games'],
            'additionalProperties' => true,
        ];

        return [
            'model' => config('fas.discovery.research_agent.model', config('openrouter.research_model')),
            'tools' => [],
            'plugins' => [
                ['id' => 'web'],
            ],
            'search_strategy' => 'WEB_PLUGIN',
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'fas_research_agent_result',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];
    }

    private function buildAgentPrompt(string $date, array $fixtures, string $cutoffAt): string
    {
        $fixturesJson = json_encode($fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $allowedEvents = implode(', ', $this->allowedEvents);

        return <<<PROMPT
Você é o agente de análise do Football Analysis System (FAS).

Data alvo: {$date}
Cutoff: {$cutoffAt}

Analise SOMENTE os jogos fornecidos abaixo em JSON.

Use pesquisa web atualizada. Não invente dados. Quando informação não estiver disponível, marque null.

Para cada jogo, pesquise quando disponível:
- últimos jogos
- momento recente
- desempenho casa/fora
- gols marcados e sofridos
- primeiro tempo
- BTTS
- Over 1.5
- Over 2.5
- cartões
- escanteios
- finalizações
- standings
- H2H
- contexto competitivo
- desfalques
- mando
- importância da partida

Não use odds como fonte principal de probabilidade.
Separe fato pesquisado de interpretação.

Eventos permitidos: {$allowedEvents}

Regras:
- Se um jogo não tiver dados suficientes, omita-o do array games
- TOP3 = melhores eventos absolutos, máx 1 evento por fixture, não force 3 (pode ser 1 ou 2)
- TOP5 = TOP3 + candidatos adicionais, máx 1 evento por fixture, não force 5
- best_games = pode ter VÁRIOS eventos da mesma partida (Over 1.5, Gol HT, BTTS, etc)
- ranking = lista de jogos com strength (VERY_HIGH/HIGH/MEDIUM) e summary
- probabilities são estimativas do agente; confidence: LOW/MEDIUM/HIGH/VERY_HIGH
- cada jogo deve ter pelo menos alguma evidência web válida em sources

Jogos a analisar:
{$fixturesJson}

Retorne JSON estrito com estrutura:
{
  "games": [ { fixture_id, home, away, competition, kickoff, quality, events: [ { event_type, label, estimated_probability, confidence, reason, sources } ], sources } ],
  "top3": [ { fixture_id, event_type, estimated_probability, confidence, reason } ],
  "top5": [ { fixture_id, event_type, estimated_probability, confidence, reason } ],
  "best_games": [ { fixture_id, events: [...] } ],
  "ranking": [ { fixture_id, strength, summary, strong_events: [...] } ]
}
PROMPT;
    }

    private function normalizeResult(array $parsed, array $input): array
    {
        $games = [];
        $top3 = [];
        $top5 = [];
        $bestGames = [];
        $ranking = [];

        // Normaliza games
        foreach (($parsed['games'] ?? []) as $rawGame) {
            $fixtureId = (string) ($rawGame['fixture_id'] ?? '');
            if (! $fixtureId) {
                continue;
            }

            $events = [];
            foreach (($rawGame['events'] ?? []) as $rawEvent) {
                $eventType = $rawEvent['event_type'] ?? null;
                if (! in_array($eventType, $this->allowedEvents, true)) {
                    continue;
                }

                $prob = (float) ($rawEvent['estimated_probability'] ?? 0);
                if ($prob <= 0 || $prob > 1) {
                    continue;
                }

                $events[] = [
                    'event_type' => $eventType,
                    'label' => $rawEvent['label'] ?? $eventType,
                    'estimated_probability' => round($prob, 4),
                    'confidence' => $rawEvent['confidence'] ?? 'MEDIUM',
                    'reason' => $rawEvent['reason'] ?? null,
                    'sources' => $rawEvent['sources'] ?? [],
                    'probability_source' => config('fas.discovery.research_agent.probability_source', 'RESEARCH_AGENT'),
                    'calibration_status' => config('fas.discovery.research_agent.calibration_status', 'UNCALIBRATED'),
                ];
            }

            if (empty($events)) {
                continue;
            }

            $games[] = [
                'fixture_id' => $fixtureId,
                'home' => $rawGame['home'] ?? '',
                'away' => $rawGame['away'] ?? '',
                'competition' => $rawGame['competition'] ?? '',
                'kickoff' => $rawGame['kickoff'] ?? '',
                'quality' => $rawGame['quality'] ?? 'MEDIUM',
                'events' => $events,
                'sources' => $rawGame['sources'] ?? [],
            ];
        }

        // Normaliza top3 — 1 evento por fixture
        $seenTop = [];
        foreach (($parsed['top3'] ?? []) as $rawTop) {
            $fixtureId = (string) ($rawTop['fixture_id'] ?? '');
            if (! $fixtureId || isset($seenTop[$fixtureId])) {
                continue;
            }

            $prob = (float) ($rawTop['estimated_probability'] ?? 0);
            if ($prob <= 0 || $prob > 1) {
                continue;
            }

            $eventType = $rawTop['event_type'] ?? null;
            if (! in_array($eventType, $this->allowedEvents, true)) {
                continue;
            }

            $seenTop[$fixtureId] = true;
            $top3[] = [
                'fixture_id' => $fixtureId,
                'event_type' => $eventType,
                'label' => $rawTop['label'] ?? $this->eventLabel($eventType),
                'estimated_probability' => round($prob, 4),
                'confidence' => $rawTop['confidence'] ?? 'MEDIUM',
                'reason' => $rawTop['reason'] ?? null,
            ];
        }

        // Normaliza top5
        $seenTop5 = $seenTop;
        foreach (($parsed['top5'] ?? []) as $rawTop) {
            $fixtureId = (string) ($rawTop['fixture_id'] ?? '');
            if (! $fixtureId || isset($seenTop5[$fixtureId])) {
                continue;
            }

            $prob = (float) ($rawTop['estimated_probability'] ?? 0);
            if ($prob <= 0 || $prob > 1) {
                continue;
            }

            $eventType = $rawTop['event_type'] ?? null;
            if (! in_array($eventType, $this->allowedEvents, true)) {
                continue;
            }

            $seenTop5[$fixtureId] = true;
            $top5[] = [
                'fixture_id' => $fixtureId,
                'event_type' => $eventType,
                'label' => $rawTop['label'] ?? $this->eventLabel($eventType),
                'estimated_probability' => round($prob, 4),
                'confidence' => $rawTop['confidence'] ?? 'MEDIUM',
                'reason' => $rawTop['reason'] ?? null,
            ];
        }

        // best_games — vários eventos por fixture
        foreach (($parsed['best_games'] ?? []) as $rawBg) {
            $fixtureId = (string) ($rawBg['fixture_id'] ?? '');
            $game = collect($games)->firstWhere('fixture_id', $fixtureId);
            if (! $game) {
                continue;
            }

            $bestGames[] = [
                'fixture_id' => $fixtureId,
                'home' => $game['home'],
                'away' => $game['away'],
                'competition' => $game['competition'],
                'events' => $game['events'],
            ];
        }

        // ranking
        foreach (($parsed['ranking'] ?? []) as $rawRank) {
            $fixtureId = (string) ($rawRank['fixture_id'] ?? '');
            $game = collect($games)->firstWhere('fixture_id', $fixtureId);
            if (! $game) {
                continue;
            }

            $ranking[] = [
                'fixture_id' => $fixtureId,
                'home' => $game['home'],
                'away' => $game['away'],
                'competition' => $game['competition'],
                'strength' => $rawRank['strength'] ?? 'MEDIUM',
                'summary' => $rawRank['summary'] ?? '',
                'strong_events' => $rawRank['strong_events'] ?? [],
            ];
        }

        // Se top3/top5 vierem vazios do agente, derivar dos games
        if (empty($top3)) {
            $top3 = $this->deriveTopEvents($games, 3);
        }
        if (empty($top5)) {
            $top5 = $this->deriveTopEvents($games, 5);
        }
        if (empty($bestGames)) {
            foreach ($games as $game) {
                $bestGames[] = [
                    'fixture_id' => $game['fixture_id'],
                    'home' => $game['home'],
                    'away' => $game['away'],
                    'competition' => $game['competition'],
                    'events' => $game['events'],
                ];
            }
        }

        return [
            'games' => $games,
            'top3' => $top3,
            'top5' => $top5,
            'best_games' => $bestGames,
            'ranking' => $ranking,
            'metadata' => [
                'probability_source' => config('fas.discovery.research_agent.probability_source', 'RESEARCH_AGENT'),
                'calibration_status' => config('fas.discovery.research_agent.calibration_status', 'UNCALIBRATED'),
                'prompt_version' => config('fas.discovery.research_agent.prompt_version', '1.0.0'),
                'analysis_mode' => config('fas.discovery.analysis_mode', 'RESEARCH_AGENT'),
            ],
        ];
    }

    private function deriveTopEvents(array $games, int $limit): array
    {
        $events = [];
        foreach ($games as $game) {
            foreach ($game['events'] as $event) {
                if (in_array($event['event_type'], ['HOME_WIN', 'AWAY_WIN', 'DRAW', 'OVER_1_5', 'OVER_2_5', 'FIRST_HALF_GOAL', 'BTTS'], true)) {
                    $events[] = [
                        'fixture_id' => $game['fixture_id'],
                        'event_type' => $event['event_type'],
                        'label' => $event['label'],
                        'estimated_probability' => $event['estimated_probability'],
                        'confidence' => $event['confidence'],
                        'reason' => $event['reason'],
                    ];
                }
            }
        }

        usort($events, fn ($a, $b) => $b['estimated_probability'] <=> $a['estimated_probability']);

        $result = [];
        $seen = [];
        foreach ($events as $event) {
            if (isset($seen[$event['fixture_id']])) {
                continue;
            }
            $seen[$event['fixture_id']] = true;
            $result[] = $event;

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function eventLabel(string $type): string
    {
        return match ($type) {
            'HOME_WIN' => 'Vitória do mandante',
            'AWAY_WIN' => 'Vitória do visitante',
            'DRAW' => 'Empate',
            'OVER_1_5' => 'Over 1.5 gols',
            'OVER_2_5' => 'Over 2.5 gols',
            'FIRST_HALF_GOAL' => 'Gol no 1º tempo',
            'BTTS' => 'Ambos marcam',
            'OVER_CORNERS' => 'Over de escanteios',
            'OVER_CARDS' => 'Over de cartões',
            'TEAM_TO_SCORE_1_PLUS' => 'Time marca 1+',
            default => $type,
        };
    }

    private function estimateCost(array $usage): float
    {
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);

        return round(
            ($prompt * config('fas.discovery.cost_per_prompt_token', 0.00000015))
            + ($completion * config('fas.discovery.cost_per_completion_token', 0.00000060)),
            6
        );
    }

    private function emptyResult(string $date, ?int $window, string $message): array
    {
        return [
            'run_id' => null,
            'status' => 'EMPTY',
            'result' => [
                'games' => [],
                'top3' => [],
                'top5' => [],
                'best_games' => [],
                'ranking' => [],
                'metadata' => [],
            ],
            'debug' => [
                'fixtures_input' => 0,
                'fixtures_analyzed' => 0,
                'fixtures_rejected' => 0,
                'openrouter_calls' => 0,
                'tokens' => 0,
                'cost_usd' => 0,
                'top3_count' => 0,
                'top5_count' => 0,
                'best_games_count' => 0,
                'message' => $message,
            ],
        ];
    }
}
