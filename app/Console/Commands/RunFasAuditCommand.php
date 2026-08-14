<?php

namespace App\Console\Commands;

use App\Models\FasExecutionRun;
use App\Services\Orchestration\FasResultOrchestrator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunFasAuditCommand extends Command
{
    protected $signature = 'fas:check {date?} {--force}';

    protected $description = 'Executa o pipeline de Auditoria do FAS para uma data (Sync Results -> Audit).';

    public function handle(FasResultOrchestrator $orchestrator)
    {
        $dateStr = $this->argument('date') ?? now()->subDay()->format('Y-m-d');
        $date = Carbon::parse($dateStr);

        if ($date->isFuture()) {
            $this->error('Não há resultados a conferir para uma data futura.');

            return;
        }

        if ($this->option('force')) {
            $this->info('Force mode enabled.');
        }

        $run = FasExecutionRun::firstOrCreate(
            [
                'execution_type' => 'RESULT_AUDIT',
                'analysis_date' => $date->format('Y-m-d'),
                'status' => 'PENDING',
            ]
        );

        $this->info("Iniciando FasResultOrchestrator para {$date->format('Y-m-d')}...");

        $orchestrator->execute($run);

        // Refresh to get final status
        $run->refresh();

        if ($run->status === 'COMPLETED') {
            $this->info('Auditoria concluída com sucesso!');
            $this->line('Resumo:');
            foreach ($run->summary as $key => $val) {
                $this->line(" - {$key}: {$val}");
            }
        } else {
            $this->error("A auditoria falhou ou terminou com status: {$run->status}");
            if ($run->errors) {
                foreach ($run->errors as $error) {
                    $this->error("[{$error['step']}] {$error['message']}");
                }
            }
        }
    }
}
