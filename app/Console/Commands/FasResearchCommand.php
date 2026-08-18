<?php

namespace App\Console\Commands;

use App\Services\OpenRouter\OpenRouterResearchProvider;
use Illuminate\Console\Command;

class FasResearchCommand extends Command
{
    protected $signature = 'fas:research {date} {--home=} {--away=} {--force} {--gaps-only} {--debug}';

    protected $description = 'Run factual football research using OpenRouter web search.';

    public function handle(OpenRouterResearchProvider $provider): int
    {
        $date = (string) $this->argument('date');
        $home = $this->option('home') ?: 'Home Team';
        $away = $this->option('away') ?: 'Away Team';

        $result = $provider->buscar_dados_partida($home, $away, $date, now()->toIso8601String());

        if ($this->option('debug')) {
            $this->line(json_encode($result['_debug'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->newLine();
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
