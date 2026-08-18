<?php

namespace App\Services\OpenRouter;

use App\Contracts\FootballResearchProviderInterface;
use App\DTOs\ResearchFixtureResult;
use App\Models\Fixture;
use RuntimeException;

class OpenRouterResearchProvider implements FootballResearchProviderInterface
{
    public function __construct(
        private OpenRouterClient $client
    ) {
    }

    public function researchFixture(Fixture $fixture, ?string $cutoffAt = null): array
    {
        return $this->buscar_dados_partida(
            $fixture->homeTeam->name ?? 'Home',
            $fixture->awayTeam->name ?? 'Away',
            $fixture->fixture_date?->toDateString() ?? now()->toDateString(),
            $cutoffAt
        );
    }

    public function researchTeamHistory(string $teamName, string $date, ?string $cutoffAt = null): array
    {
        return $this->researchTeamHistoryCore($teamName, $date, $cutoffAt);
    }

    public function researchMatchContext(string $homeTeam, string $awayTeam, string $date, ?string $cutoffAt = null): array
    {
        return $this->buscar_dados_partida($homeTeam, $awayTeam, $date, $cutoffAt);
    }

    public function researchTeamHistoryCore(string $teamName, string $date, ?string $cutoffAt = null): array
    {
        $prompt = $this->buildTeamHistoryPrompt($teamName, $date, $cutoffAt);

        return $this->client->researchWebPluginJsonWithDebug($prompt, [
            'model' => config('openrouter.research_model', config('openrouter.model')),
            'tools' => [],
            'plugins' => [
                ['id' => 'web'],
            ],
            'search_strategy' => 'WEB_PLUGIN',
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'football_team_history',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'team' => ['type' => 'string'],
                            'matches' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'date' => ['type' => 'string'],
                                        'competition' => ['type' => ['string', 'null']],
                                        'home_team' => ['type' => 'string'],
                                        'away_team' => ['type' => 'string'],
                                        'home_score_ft' => ['type' => 'integer'],
                                        'away_score_ft' => ['type' => 'integer'],
                                        'home_score_ht' => ['type' => ['integer', 'null']],
                                        'away_score_ht' => ['type' => ['integer', 'null']],
                                    ],
                                    'required' => ['date', 'competition', 'home_team', 'away_team', 'home_score_ft', 'away_score_ft', 'home_score_ht', 'away_score_ht'],
                                    'additionalProperties' => true,
                                ],
                            ],
                        ],
                        'required' => ['team', 'matches'],
                        'additionalProperties' => true,
                    ],
                ],
            ],
        ]);
    }

    public function researchMissingMetrics(Fixture $fixture, array $missingFields, ?string $cutoffAt = null): array
    {
        $prompt = $this->buildPrompt(
            $fixture->homeTeam->name ?? 'Home',
            $fixture->awayTeam->name ?? 'Away',
            $fixture->fixture_date?->toDateString() ?? now()->toDateString(),
            $missingFields,
            $cutoffAt
        );

        return $this->client->researchWebPluginJsonWithDebug($prompt, [
            'model' => config('openrouter.research_model', config('openrouter.model')),
            'tools' => [],
            'plugins' => [
                ['id' => 'web'],
            ],
            'search_strategy' => 'WEB_PLUGIN',
        ])['result'];
    }

    public function discoverFixturesByDate(string $date, ?string $cutoffAt = null): array
    {
        $prompt = <<<PROMPT
Você é um agente de descoberta de jogos de futebol.

Data alvo: {$date}
Cutoff: {$cutoffAt}

Tarefa:
- descubra jogos públicos marcados para a data acima
- retorne apenas partidas verificáveis antes do kickoff
- se não houver certeza, use null
- cite URLs em "sources"
- não invente

Retorne JSON estrito:
{
  "fixtures": [
    {
      "date": "YYYY-MM-DDTHH:MM:SSZ",
      "home_team": "string",
      "away_team": "string",
      "competition": "string|null",
      "country": "string|null",
      "kickoff": "HH:MM|null",
      "source_urls": ["https://..."]
    }
  ],
  "sources": [],
  "missing_fields": [],
  "research_quality": "HIGH"
}
PROMPT;

        return $this->client->researchWebPluginJsonWithDebug($prompt, [
            'model' => config('openrouter.research_model', config('openrouter.model')),
            'tools' => [],
            'plugins' => [
                ['id' => 'web'],
            ],
            'search_strategy' => 'WEB_PLUGIN',
        ])['result'];
    }

    public function buscar_dados_partida(string $timeA, string $timeB, string $data, ?string $cutoffAt = null): array
    {
        $homeResearch = $this->researchTeamHistoryCore($timeA, $data, $cutoffAt);
        $awayResearch = $this->researchTeamHistoryCore($timeB, $data, $cutoffAt);

        $homeMatches = $homeResearch['result']['matches'] ?? [];
        $awayMatches = $awayResearch['result']['matches'] ?? [];
        $sources = array_values(array_unique(array_merge(
            $homeResearch['debug']['sources'] ?? [],
            $awayResearch['debug']['sources'] ?? []
        )));

        return (new ResearchFixtureResult(
            fixture: ['date' => $data, 'home_team' => $timeA, 'away_team' => $timeB],
            home_team: ['name' => $timeA],
            away_team: ['name' => $timeB],
            home_recent_matches: $homeMatches,
            away_recent_matches: $awayMatches,
            home_home_matches: [],
            away_away_matches: [],
            h2h: [],
            standings: [],
            injuries: [],
            suspensions: [],
            referee: [],
            weather: [],
            sources: $sources,
            missing_fields: [],
            conflicts: [],
            research_quality: count($homeMatches) >= 5 && count($awayMatches) >= 5 ? 'HIGH' : ((count($homeMatches) > 0 || count($awayMatches) > 0) ? 'PARTIAL' : 'INSUFFICIENT'),
        ))->toArray() + [
            '_debug' => [
                'home' => $homeResearch['debug'],
                'away' => $awayResearch['debug'],
                'home_matches_count' => count($homeMatches),
                'away_matches_count' => count($awayMatches),
                'sources_count' => count($sources),
                'raw_matches_count' => count($homeMatches) + count($awayMatches),
            ],
        ];
    }

    private function buildPrompt(string $homeTeam, string $awayTeam, string $date, array $focusFields = [], ?string $cutoffAt = null): string
    {
        return <<<PROMPT
Você é um agente de pesquisa esportiva factual.
Pesquise SOMENTE informações publicamente verificáveis anteriores ao kickoff.

Partida: {$homeTeam} x {$awayTeam}
Data: {$date}
Cutoff: {$cutoffAt}

Busque fatos, não probabilidades.

Campos prioritários: {$this->stringifyList($focusFields)}

Regras:
- Não calcule chance, odds ou palpite.
- Não invente dados.
- Se não encontrar um campo, use null.
- Cite URLs em "sources".
- Retorne JSON estrito.

Formato esperado:
{
  "fixture": {},
  "home_team": {},
  "away_team": {},
  "home_recent_matches": [],
  "away_recent_matches": [],
  "home_home_matches": [],
  "away_away_matches": [],
  "h2h": [],
  "standings": {},
  "injuries": [],
  "suspensions": [],
  "referee": {},
  "weather": {},
  "sources": [],
  "missing_fields": [],
  "conflicts": [],
  "research_quality": "HIGH"
}
PROMPT;
    }

    private function buildTeamHistoryPrompt(string $teamName, string $date, ?string $cutoffAt = null): string
    {
        return <<<PROMPT
Use the supplied web search results.

Find the 10 most recent COMPLETED matches involving the football club:

TEAM: {$teamName}

Only include matches strictly before:
{$cutoffAt}

Return matches, not a prose summary.

For every match return:

date
competition
home_team
away_team
home_score_ft
away_score_ft
home_score_ht if explicitly available
away_score_ht if explicitly available

Do not calculate statistics.
Do not predict anything.
Do not invent missing matches.

If only 6 valid matches are found, return 6.

The matches array MUST contain the extracted matches.
PROMPT;
    }

    private function stringifyList(array $items): string
    {
        return empty($items) ? 'none' : implode(', ', $items);
    }
}
