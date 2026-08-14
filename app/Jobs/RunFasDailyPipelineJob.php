<?php

namespace App\Jobs;

use App\Models\FasExecutionRun;
use App\Services\Orchestration\FasDailyOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunFasDailyPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour max

    protected FasExecutionRun $run;

    public function __construct(FasExecutionRun $run)
    {
        $this->run = $run;
    }

    public function handle(FasDailyOrchestrator $orchestrator): void
    {
        $orchestrator->execute($this->run);
    }
}
