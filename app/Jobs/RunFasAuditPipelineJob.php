<?php

namespace App\Jobs;

use App\Models\FasExecutionRun;
use App\Services\Orchestration\FasResultOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunFasAuditPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    protected FasExecutionRun $run;

    public function __construct(FasExecutionRun $run)
    {
        $this->run = $run;
    }

    public function handle(FasResultOrchestrator $orchestrator): void
    {
        $orchestrator->execute($this->run);
    }
}
