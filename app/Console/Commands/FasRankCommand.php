<?php

namespace App\Console\Commands;

use App\Jobs\GenerateFasRankingJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FasRankCommand extends Command
{
    protected $signature = 'fas:rank {date?} {--force} {--analyze-missing}';

    protected $description = 'Gera o Ranking diário do FAS Engine V1';

    public function handle()
    {
        $dateStr = $this->argument('date') ?? Carbon::today()->toDateString();
        $force = $this->option('force');
        $analyzeMissing = $this->option('analyze-missing');

        $this->info("Iniciando geração de Ranking FAS para $dateStr...");

        if ($analyzeMissing) {
            $this->info('Analisando fixtures faltantes (chamando fas:analyze)');
            $this->call('fas:analyze', [
                'date' => $dateStr,
                '--build-missing-dataset' => true,
            ]);
        }

        GenerateFasRankingJob::dispatchSync($dateStr, $force);

        $this->info('Ranking gerado com sucesso.');
    }
}
