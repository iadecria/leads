<?php

namespace App\Console\Commands;

use App\Models\FasExecutionRun;
use App\Services\Orchestration\FasDailyOrchestrator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunFasDailyCommand extends Command
{
    protected $signature = 'fas:run {date?} {--force}';

    protected $description = 'Executa o pipeline completo do FAS para uma data (Fixtures -> Datasets -> Analysis -> Ranking).';

    public function handle(FasDailyOrchestrator $orchestrator)
    {
        $dateStr = $this->argument('date') ?? now()->format('Y-m-d');
        $date = Carbon::parse($dateStr);

        if ($this->option('force')) {
            $this->info('Force mode enabled.');
        }

        $run = FasExecutionRun::firstOrCreate(
            [
                'execution_type' => 'DAILY_ANALYSIS',
                'analysis_date' => $date->format('Y-m-d'),
                'status' => 'PENDING',
            ]
        );

        $this->info("Iniciando FasDailyOrchestrator para {$date->format('Y-m-d')}...");

        $orchestrator->execute($run);

        // Refresh to get final status
        $run->refresh();

        if ($run->status === 'COMPLETED') {
            $this->info('Execução concluída com sucesso!');
            $this->line('Resumo:');
            foreach ($run->summary as $key => $val) {
                $this->line(" - {$key}: {$val}");
            }
        } else {
            $this->error("A execução falhou ou terminou com status: {$run->status}");
            if ($run->errors) {
                foreach ($run->errors as $error) {
                    $this->error("[{$error['step']}] {$error['message']}");
                }
            }
        }
    }
}
