<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FasApiStatus extends Command
{
    protected $signature = 'fas:api-status';

    protected $description = 'Check API-Football connection status and limits';

    public function handle()
    {
        $this->info('Checking API-Football Status...');

        $baseUrl = config('api-football.base_url');
        $key = config('api-football.key');

        if (empty($key)) {
            $this->error('API Key is not configured.');

            return Command::FAILURE;
        }

        try {
            $response = Http::withHeaders([
                'x-apisports-key' => $key,
            ])->get(rtrim($baseUrl, '/').'/status');

            if ($response->successful()) {
                $data = $response->json();
                $this->info('API-Football: Conectado');

                $subscription = $data['response']['subscription'] ?? null;
                $requests = $data['response']['requests'] ?? null;

                if ($subscription) {
                    $this->line('Plano: '.$subscription['plan']);
                }

                if ($requests) {
                    $this->line("Requests hoje: {$requests['current']} / {$requests['limit_day']}");
                }

                // Check cache stats from our DB
                $cacheHits = ApiRequestLog::sum('cache_hits');
                $cacheMisses = ApiRequestLog::sum('cache_misses');
                $total = $cacheHits + $cacheMisses;
                $hitRate = $total > 0 ? round(($cacheHits / $total) * 100, 2) : 0;

                $this->line("Cache Hits: {$cacheHits}");
                $this->line("Cache Misses: {$cacheMisses}");
                $this->line("Cache Hit Rate: {$hitRate}%");

            } else {
                $this->error('Falha ao conectar: HTTP '.$response->status());
            }

        } catch (\Exception $e) {
            $this->error('Erro de conexão: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
