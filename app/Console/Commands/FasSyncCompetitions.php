<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\CompetitionSyncService;
use Illuminate\Console\Command;

class FasSyncCompetitions extends Command
{
    protected $signature = 'fas:sync-competitions {--season= : The season year to sync (defaults to current year)}';

    protected $description = 'Sync competitions and coverage from API-Football';

    public function handle(CompetitionSyncService $syncService)
    {
        $season = $this->option('season') ?: date('Y');
        $this->info("Starting competition sync for season {$season}...");

        try {
            $count = $syncService->sync((int) $season);
            $this->info("Successfully synced {$count} competitions.");
        } catch (\Exception $e) {
            $this->error('Failed to sync: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
