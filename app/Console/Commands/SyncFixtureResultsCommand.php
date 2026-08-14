<?php

namespace App\Console\Commands;

use App\Services\FootballData\FixtureResultSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncFixtureResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fas:sync-results {date} {--audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync post-match results for fixtures on a given date';

    /**
     * Execute the console command.
     */
    public function handle(FixtureResultSyncService $syncService)
    {
        $date = $this->argument('date');
        $this->info("Syncing results for {$date}...");

        $syncService->syncResultsForDate($date);

        $this->info("Sync complete for {$date}.");

        if ($this->option('audit')) {
            $this->info("Calling fas:audit for {$date}...");
            Artisan::call('fas:audit', [
                'date' => $date,
            ], $this->output);
        }
    }
}
