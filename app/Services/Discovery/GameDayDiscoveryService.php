<?php

namespace App\Services\Discovery;

use App\Services\OpenRouter\OpenRouterClient;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GameDayDiscoveryService
{
    private string $timezone;

    public function __construct(
        private OpenRouterClient $client
    ) {
        $this->timezone = config('fas.discovery.timezone', 'America/Sao_Paulo');
    }

    public function discover(string $date): array
    {
        $selectedDate = Carbon::parse($date, $this->timezone);
        $cutoffAt = now()->setTimezone($this->timezone)->toIso8601String();

        // 1. Executa busca por bloco (Europa, Brasil, Américas)
        $blockResults = $this->discoverByBlocks($date, $cutoffAt, $selectedDate);

        // 2. Merge e deduplicação
        [$merged, $deduplicatedFixtures, $dedupCount] = $this->mergeBlocks($blockResults['all_fixtures']);
        $deduplicated = count($deduplicatedFixtures);

        // 3. Filtro FAS (exclusões)
        [$eligible, $excluded] = $this->partitionFixtures($deduplicatedFixtures, $selectedDate);

        // 4. Para hoje, remove jogos que já começaram
        $isToday = $selectedDate->isToday(new \DateTimeZone($this->timezone));
        if ($isToday) {
            $eligible = $this->removeStartedFixtures($eligible);
        }

        // 5. Ordena por relevância e separa em Janela 1 / Janela 2
        $windows = $this->sortAndWindow($eligible);

        $totals = $this->summarizeCosts($blockResults['debug_list']);
        $sources = array_values(array_unique($blockResults['sources']));

        return [
            'date' => $selectedDate->toDateString(),
            'cutoff_at' => $cutoffAt,
            'is_today' => $isToday,
            'discovery_status' => count($eligible) > 0 ? 'DISCOVERY_SUCCESS' : 'DISCOVERY_EMPTY',

            // Debug por bloco
            'discovery_europa' => $blockResults['counts']['europa_elite'],
            'discovery_brasil' => $blockResults['counts']['brasil'],
            'discovery_americas' => $blockResults['counts']['americas'],
            'block_debug' => $blockResults['debug_list'],

            'merged' => $merged,
            'deduplicated' => $deduplicated,
            'dedup_removed' => $dedupCount,

            'fixtures_found' => $merged,
            'fixtures_eligible' => count($eligible),
            'fixtures_excluded' => count($excluded),
            'excluded_fixtures' => array_slice($excluded, 0, 10),

            'window_1' => $windows['window_1'],
            'window_2' => $windows['window_2'],
            'selected_count' => count($windows['window_1']) + count($windows['window_2']),

            // Custos
            'calls' => $totals['calls'],
            'tokens' => $totals['tokens'],
            'prompt_tokens' => $totals['prompt_tokens'],
            'completion_tokens' => $totals['completion_tokens'],
            'estimated_cost_usd' => round($totals['cost'], 6),
            'sources' => $sources,
        ];
    }

    /**
     * Executa uma busca leve por bloco de competição.
     */
    private function discoverByBlocks(string $date, string $cutoffAt, Carbon $selectedDate): array
    {
        $blocks = config('fas.discovery.blocks', []);
        $allFixtures = [];
        $allSources = [];
        $counts = [];
        $debugList = [];

        foreach ($blocks as $blockKey => $blockConfig) {
            $label = $blockConfig['label'] ?? $blockKey;
            $competitions = $blockConfig['competitions'] ?? '';

            try {
                $result = $this->client->researchWebPluginJsonWithDebug(
                    $this->buildBlockPrompt($date, $cutoffAt, $label, $competitions),
                    $this->requestOptions()
                );

                $parsed = $result['result'] ?? [];
                $debug = $result['debug'] ?? [];

                $fixtures = $parsed['fixtures'] ?? [];
                $sources = array_values(array_unique(array_merge(
                    $debug['sources'] ?? [],
                    $parsed['sources'] ?? []
                )));

                $counts[$blockKey] = count($fixtures);
                $allFixtures = array_merge($allFixtures, $fixtures);
                $allSources = array_merge($allSources, $sources);

                $debugList[] = [
                    'block' => $blockKey,
                    'label' => $label,
                    'found' => count($fixtures),
                    'usage' => $debug['usage'] ?? [],
                    'web_search_executed' => (bool) ($debug['web_search_executed'] ?? false),
                ];
            } catch (\Throwable $e) {
                $counts[$blockKey] = 0;
                $debugList[] = [
                    'block' => $blockKey,
                    'label' => $label,
                    'found' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'all_fixtures' => $allFixtures,
            'sources' => $allSources,
            'counts' => $counts,
            'debug_list' => $debugList,
        ];
    }

    private function requestOptions(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'fixtures' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'competition' => ['type' => 'string'],
                            'country' => ['type' => ['string', 'null']],
                            'home_team' => ['type' => 'string'],
                            'away_team' => ['type' => 'string'],
                            'kickoff' => ['type' => 'string'],
                            'kickoff_utc' => ['type' => ['string', 'null']],
                            'venue' => ['type' => ['string', 'null']],
                            'stage' => ['type' => ['string', 'null']],
                            'round' => ['type' => ['string', 'null']],
                            'source_urls' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['competition', 'country', 'home_team', 'away_team', 'kickoff'],
                        'additionalProperties' => true,
                    ],
                ],
                'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['fixtures', 'sources'],
            'additionalProperties' => true,
        ];

        return [
            'model' => config('openrouter.research_model', config('openrouter.model')),
            'tools' => [],
            'plugins' => [
                ['id' => 'web'],
            ],
            'search_strategy' => 'WEB_PLUGIN',
            'max_tokens' => config('openrouter.discovery_max_tokens', 8000),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'gameday_discovery_'.$this->randomSuffix(),
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];
    }

    private function buildBlockPrompt(string $date, string $cutoffAt, string $label, string $competitions): string
    {
        return <<<PROMPT
Find ALL competitive football (soccer) matches scheduled for the date {$date} in block: {$label}.

CUTOFF: {$cutoffAt}

Search the web specifically for these competitions:
{$competitions}

IMPORTANT:
- Include qualifying/playoff rounds for UEFA competitions (Champions League, Europa League, Conference League)
- Return every match you can verify, even if kickoff is early in the day
- Do NOT restrict to prime-time matches
- Do NOT return matches from excluded categories: Libertadores, Série B, friendlies/amistosos, women football, youth/reserve teams

For each match return:
- competition: official competition name (e.g., "UEFA Champions League Qualifying", "Premier League", "Brasileirão Série A")
- country: country of the competition
- home_team: full home team name
- away_team: full away team name
- kickoff: local kickoff in "HH:MM" (America/Sao_Paulo timezone) — convert from the match's own timezone
- kickoff_utc: kickoff in ISO8601 UTC (e.g., "19:00 UTC" → "19:00", "2026-08-19T19:00:00Z") if known
- venue: stadium name if found
- stage: "Group Stage", "Qualifying Round 2", "Final", "Semi-final", etc.
- round: round number if known (League matches)
- source_urls: URLs where this fixture is confirmed

Rules:
- Do NOT invent fixtures — only include ones found on the web
- If kickoff time is unknown, set kickoff to "23:59"
- Return strict JSON only
PROMPT;
    }

    private function randomSuffix(): string
    {
        return substr(md5(uniqid()), 0, 8);
    }

    /**
     * Merge fixturas de todos os blocos e deduplica.
     */
    private function mergeBlocks(array $rawFixtures): array
    {
        $merged = count($rawFixtures);
        $dedupMap = [];
        $unique = [];

        foreach ($rawFixtures as $raw) {
            $home = $this->normalizeTeamName($raw['home_team'] ?? '');
            $away = $this->normalizeTeamName($raw['away_team'] ?? '');
            $dateKey = $raw['kickoff'] ?? '';

            $key = $dateKey.'|'.$home.'|'.$away;

            if (isset($dedupMap[$key])) {
                continue;
            }

            $dedupMap[$key] = true;
            $unique[] = $raw;
        }

        return [$merged, $unique, $merged - count($unique)];
    }

    private function normalizeTeamName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->trim()
            ->replaceMatches('/\b(fc|sc|cf|ac|cd|af|de|real|club|glimt)\b/', ' ')
            ->squish()
            ->trim()
            ->toString();
    }

    /**
     * Separa elegíveis e excluídos; normaliza kickoff para America/Sao_Paulo.
     */
    private function partitionFixtures(array $rawFixtures, Carbon $selectedDate): array
    {
        $eligible = [];
        $excluded = [];

        foreach ($rawFixtures as $raw) {
            $competition = $raw['competition'] ?? '';

            if ($this->isExcluded($competition)) {
                $excluded[] = $this->normalizeDiscoveredFixture($raw, $selectedDate);
                continue;
            }

            $eligible[] = $this->normalizeDiscoveredFixture($raw, $selectedDate);
        }

        return [$eligible, $excluded];
    }

    private function removeStartedFixtures(array $eligible): array
    {
        $nowInTz = now()->setTimezone($this->timezone);

        return array_values(array_filter($eligible, function ($fixture) use ($nowInTz) {
            return Carbon::parse($fixture['kickoff'], $this->timezone)->greaterThan($nowInTz);
        }));
    }

    private function sortAndWindow(array $eligible): array
    {
        foreach ($eligible as &$fixture) {
            $fixture['discovery_score'] = $this->computeDiscoveryScore($fixture);
        }
        unset($fixture);

        usort($eligible, fn ($a, $b) => $b['discovery_score'] <=> $a['discovery_score']);

        $window1 = array_values(array_filter($eligible, fn ($f) => $f['window'] === 1));
        $window2 = array_values(array_filter($eligible, fn ($f) => $f['window'] === 2));

        $maxPerWindow = (int) config('fas.discovery.max_per_window', 10);
        $window1 = array_slice($window1, 0, $maxPerWindow);
        $window2 = array_slice($window2, 0, $maxPerWindow);

        return [
            'all' => $eligible,
            'window_1' => $window1,
            'window_2' => $window2,
        ];
    }

    private function normalizeDiscoveredFixture(array $raw, Carbon $selectedDate): array
    {
        $competition = $raw['competition'] ?? 'Unknown';
        $country = $raw['country'] ?? null;
        $kickoffRaw = $raw['kickoff'] ?? '23:59';
        $kickoffUtc = $raw['kickoff_utc'] ?? null;

        // Conversão de timezone: se vier kickoff_utc, usa ele; senão assume HH:MM no fuso America/Sao_Paulo
        $kickoff = $this->resolveKickoff($kickoffRaw, $kickoffUtc, $selectedDate);

        return [
            'competition' => $competition,
            'country' => $country,
            'home_team' => $raw['home_team'] ?? 'Unknown',
            'away_team' => $raw['away_team'] ?? 'Unknown',
            'kickoff' => $kickoff->format('Y-m-d H:i'),
            'kickoff_time' => $kickoff->format('H:i'),
            'venue' => $raw['venue'] ?? null,
            'stage' => $raw['stage'] ?? null,
            'round' => $raw['round'] ?? null,
            'source_urls' => $raw['source_urls'] ?? [],
            'window' => $this->windowForKickoff($kickoff),
            'discovery_score' => 0,
        ];
    }

    /**
     * Converte kickoff para America/Sao_Paulo.
     *
     * Se kickoff_utc existir, usa como fonte (ex: "19:00" = 19:00 UTC → 16:00 BRT).
     * Senão interpreta kickoff como HH:MM já em America/Sao_Paulo.
     */
    private function resolveKickoff(string $kickoffRaw, ?string $kickoffUtc, Carbon $selectedDate): Carbon
    {
        if ($kickoffUtc && $kickoffUtc !== '') {
            try {
                // Suporta "19:00", "19:00 UTC", "2026-08-19T19:00:00Z", "2026-08-19 19:00", "19:00Z"
                $utcKickoff = $this->normalizeUtcKickoff($kickoffUtc);

                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $utcKickoff)) {
                    // Só hora: assume a data alvo + hora UTC
                    $utc = Carbon::parse($selectedDate->format('Y-m-d').' '.substr($utcKickoff, 0, 5).':00', 'UTC');
                } else {
                    // Data completa ou datetime
                    $utc = Carbon::parse($utcKickoff, 'UTC');
                }

                return $utc->copy()->setTimezone($this->timezone);
            } catch (\Throwable) {
                // fallback para tratamento abaixo
            }
        }

        // Interpreta como HH:MM já em America/Sao_Paulo
        $time = preg_match('/^\d{2}:\d{2}/', $kickoffRaw) ? substr($kickoffRaw, 0, 5) : '23:59';

        return Carbon::parse($selectedDate->format('Y-m-d').' '.$time, $this->timezone);
    }

    private function normalizeUtcKickoff(string $raw): string
    {
        $trimmed = trim($raw);

        // "19:00" ou "19:00:00"
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $trimmed)) {
            return $trimmed;
        }

        // "19:00 UTC" ou "19:00:00 UTC" ou "19:00Z"
        if (preg_match('/^(\d{2}:\d{2}(:\d{2})?)\s*(UTC|Z)?$/i', $trimmed, $m)) {
            return $m[1];
        }

        // "2026-08-19T19:00:00Z" ou "2026-08-19 19:00" etc.
        return $trimmed;
    }

    private function windowForKickoff(Carbon $kickoff): int
    {
        $cutoff = config('fas.discovery.cutoff_time', '17:00');

        return $kickoff->format('H:i') < $cutoff ? 1 : 2;
    }

    private function computeDiscoveryScore(array $fixture): int
    {
        $competition = $fixture['competition'] ?? '';
        $stage = $fixture['stage'] ?? '';
        $lower = Str::lower($competition.' '.$stage);

        $tierScore = 0;
        foreach (config('fas.discovery.tier_scores', []) as $tier => $score) {
            foreach (config("fas.discovery.tiers.{$tier}", []) as $pattern) {
                if (Str::contains($lower, $pattern)) {
                    $tierScore = max($tierScore, $score);
                    break 2;
                }
            }
        }

        $bonus = 0;
        foreach (config('fas.discovery.knockout_patterns', []) as $pattern) {
            if (Str::contains($lower, $pattern)) {
                $bonus = (int) config('fas.discovery.knockout_bonus', 15);
                break;
            }
        }

        return $tierScore + $bonus;
    }

    private function isExcluded(string $competition): bool
    {
        $lower = Str::lower($competition);

        foreach (config('fas.discovery.excluded_patterns', []) as $pattern) {
            if (Str::contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function summarizeCosts(array $debugList): array
    {
        $calls = 0;
        $tokens = 0;
        $promptTokens = 0;
        $completionTokens = 0;

        foreach ($debugList as $entry) {
            $usage = $entry['usage'] ?? [];
            if (empty($usage)) {
                continue;
            }

            if (! empty($entry['error'])) {
                continue;
            }

            $calls++;
            $promptTokens += (int) ($usage['prompt_tokens'] ?? 0);
            $completionTokens += (int) ($usage['completion_tokens'] ?? 0);
            $tokens += (int) ($usage['total_tokens'] ?? 0);
        }

        $cost = ($promptTokens * config('fas.discovery.cost_per_prompt_token', 0.00000015))
            + ($completionTokens * config('fas.discovery.cost_per_completion_token', 0.00000060));

        return [
            'calls' => $calls,
            'tokens' => $tokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost' => $cost,
        ];
    }
}