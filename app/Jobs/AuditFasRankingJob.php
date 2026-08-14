<?php

namespace App\Jobs;

use App\Models\FasRankingRun;
use App\Services\Audit\FasAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AuditFasRankingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public FasRankingRun $run;

    public bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(FasRankingRun $run, bool $force = false)
    {
        $this->run = $run;
        $this->force = $force;
    }

    /**
     * Execute the job.
     */
    public function handle(FasAuditService $auditService): void
    {
        $auditService->auditRun($this->run, $this->force);
    }
}
