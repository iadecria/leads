<?php

namespace App\Console\Commands;

use App\Models\FasExecutionRun;
use App\Services\Orchestration\FasDailyOrchestrator;
use App\Services\OpenRouter\OpenRouterResearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunFasDailyCommand extends Command
{
    protected $signature = 'fas:run {date?} {--force}';

    protected $description = 'Executa o pipeline completo do FAS para uma data (Fixtures -> Datasets -> Analysis -> Ranking).';

    public function handle(FasDailyOrchestrator $orchestrator, OpenRouterResearchService $researchService)
    {
        $dateStr = $this->argument('date') ?? now()->format('Y-m-d');
        $date = Carbon::parse($dateStr);

        if ($this->option('force')) {
            $this->info('Force mode enabled.');
        }

        $run = FasExecutionRun::create([
            'execution_type' => 'DAILY_ANALYSIS',
            'analysis_date' => $date->format('Y-m-d'),
            'status' => 'PENDING',
        ]);

        $this->info("Iniciando FasDailyOrchestrator para {$date->format('Y-m-d')}...");

        $orchestrator->execute($run, $researchService);

        // Refresh to get final status
        $run->refresh();

        if ($run->status === 'COMPLETED') {
            $this->info('Execução concluída com sucesso!');
            $this->line('Resumo:');
            foreach ($run->summary as $key => $val) {
                $this->line(' - '.$key.': '.(is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val));
            }
        } else {
            $this->error("A execução falhou ou terminou com status: {$run->status}");
            if ($run->errors) {
                foreach ($run->errors as $error) {
                    $step = $error['step'] ?? 'unknown';
                    $message = $error['message'] ?? json_encode($error, JSON_UNESCAPED_UNICODE);
                    $this->error("[{$step}] {$message}");
                }
            }
        }
    }
}
