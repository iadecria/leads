<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\FixtureSyncService;
use Illuminate\Console\Command;

class FasSyncFixtures extends Command
{
    protected $signature = 'fas:sync-fixtures {date : Date in YYYY-MM-DD format}';

    protected $description = 'Sync fixtures for a specific date from API-Football';

    public function handle(FixtureSyncService $syncService)
    {
        $date = $this->argument('date');
        $this->info("Starting fixture sync for date {$date}...");

        try {
            $count = $syncService->syncByDate($date);
            $this->info("Successfully synced {$count} fixtures.");
        } catch (\Exception $e) {
            $this->error('Failed to sync: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
