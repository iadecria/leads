<?php

namespace App\Console\Commands;

use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Console\Command;

class FasDiscoverDebugCommand extends Command
{
    protected $signature = 'fas:discover-debug {date}';

    protected $description = 'Debug: executar os 3 blocos e expor resposta crua do OpenRouter';

    public function handle(OpenRouterClient $client): int
    {
        $date = (string) $this->argument('date');
        $timezone = config('fas.discovery.timezone', 'America/Sao_Paulo');
        $cutoffAt = now()->setTimezone($timezone)->toIso8601String();
        $blocks = config('fas.discovery.blocks', []);

        foreach ($blocks as $blockKey => $blockConfig) {
            $label = $blockConfig['label'] ?? $blockKey;
            $competitions = $blockConfig['competitions'] ?? '';

            $this->info("=== BLOCK: {$label} ({$blockKey}) ===");

            try {
                $result = $client->researchWebPluginJsonWithDebug(
                    $this->buildPrompt($date, $cutoffAt, $label, $competitions),
                    $this->requestOptions()
                );

                $parsed = $result['result'] ?? [];
                $debug = $result['debug'] ?? [];
                $usage = $debug['usage'] ?? [];
                $prompt = (int) ($usage['prompt_tokens'] ?? 0);
                $completion = (int) ($usage['completion_tokens'] ?? 0);
                $cost = round(
                    ($prompt * config('fas.discovery.cost_per_prompt_token', 0.00000015))
                    + ($completion * config('fas.discovery.cost_per_completion_token', 0.00000060)),
                    6
                );

                $this->line("MODEL_REQUESTED: ".($debug['requested_model'] ?? 'N/A'));
                $this->line("MODEL_RESOLVED: ".($debug['resolved_model'] ?? 'N/A'));
                $this->line("SEARCH_STRATEGY: ".($debug['search_strategy'] ?? 'N/A'));
                $this->line("WEB_SEARCH_EXECUTED: ".($debug['web_search_executed'] ?? false ? 'true' : 'false'));
                $this->line("TOKENS: prompt={$prompt} completion={$completion} total=".(int) ($usage['total_tokens'] ?? 0));
                $this->line("COST: US$ {$cost}");

                $content = (string) ($debug['content'] ?? '');
                $this->line("RAW_CONTENT_LENGTH: ".strlen($content));

                $this->newLine();
                $this->warn('---- RAW CONTENT ----');
                $this->line($content);
                $this->warn('---- END RAW CONTENT ----');

                $this->newLine();
                $this->warn('---- PARSED JSON KEYS ----');
                $this->line(implode(', ', array_keys($parsed)));
                $this->warn('---- PARSED ----');
                $this->line(json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $this->warn('---- END PARSED ----');

                $fixtures = $parsed['fixtures'] ?? [];
                $this->newLine();
                $this->line("FIXTURES NO SCHEMA 'fixtures': ".count($fixtures));
                foreach ($fixtures as $i => $f) {
                    $this->line("  [{$i}] ".json_encode($f, JSON_UNESCAPED_UNICODE));
                }

                foreach (['games', 'matches', 'events', 'results'] as $altKey) {
                    if (isset($parsed[$altKey]) && is_array($parsed[$altKey]) && $altKey !== 'fixtures') {
                        $this->newLine();
                        $this->warn("!!! CHAVE ALTERNATIVA '{$altKey}' com ".count($parsed[$altKey])." itens !!!");
                        foreach ($parsed[$altKey] as $i => $f) {
                            $this->line("  [{$i}] ".json_encode($f, JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->error('ERRO: '.$e->getMessage());
            }

            $this->newLine();
            $this->newLine();
        }

        return self::SUCCESS;
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
            'plugins' => [['id' => 'web']],
            'search_strategy' => 'WEB_PLUGIN',
            'max_tokens' => config('openrouter.discovery_max_tokens', 8000),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'gameday_discovery_'.substr(md5(uniqid()), 0, 8),
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];
    }

    private function buildPrompt(string $date, string $cutoffAt, string $label, string $competitions): string
    {
        return <<<PROMPT
Find ALL competitive football (soccer) matches scheduled for the date {$date} in block: {$label}.

CUTOFF: {$cutoffAt}

Search the web specifically for these competitions:
{$competitions}

IMPORTANT:
- Include qualifying/playoff rounds for UEFA competitions
- Return every match you can verify, even if kickoff is early in the day
- Do NOT return excluded categories: Libertadores, Série B, friendlies, women, youth/reserve

For each match return:
- competition, country, home_team, away_team
- kickoff: local HH:MM (America/Sao_Paulo)
- kickoff_utc: ISO8601 UTC if known
- venue, stage, round, source_urls

Rules:
- Do NOT invent fixtures
- If kickoff time is unknown, set to "23:59"
- Return strict JSON only
PROMPT;
    }
}