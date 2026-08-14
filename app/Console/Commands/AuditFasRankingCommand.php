<?php

namespace App\Console\Commands;

use App\Jobs\AuditFasRankingJob;
use App\Models\FasRankingRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AuditFasRankingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fas:audit {date} {--ranking-run=} {--sync-results} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audits a FAS Ranking against actual results';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');
        $runId = $this->option('ranking-run');

        if ($this->option('sync-results')) {
            $this->info('Syncing results first...');
            Artisan::call('fas:sync-results', ['date' => $date], $this->output);
        }

        $query = FasRankingRun::whereDate('analysis_date', $date);
        if ($runId) {
            $query->where('id', $runId);
        } else {
            // Get the latest run for the day
            $query->orderBy('created_at', 'desc');
        }

        $run = $query->first();

        if (! $run) {
            $this->error("No Ranking Run found for date {$date}.");

            return;
        }

        $this->info("Dispatching Audit Job for Run #{$run->id}...");

        AuditFasRankingJob::dispatch($run, $this->option('force'));

        $this->info('Job dispatched.');
    }
}
