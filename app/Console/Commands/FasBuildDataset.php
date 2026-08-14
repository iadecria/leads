<?php

namespace App\Console\Commands;

use App\Jobs\BuildMatchDatasetJob;
use App\Models\Fixture;
use Illuminate\Console\Command;

class FasBuildDataset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fas:build-dataset {date} {--fixture=} {--force} {--sync-missing}';

    protected $description = 'Build match dataset for a specific date or fixture';

    public function handle()
    {
        $date = $this->argument('date');
        $fixtureId = $this->option('fixture');
        $force = $this->option('force');
        $syncMissing = $this->option('sync-missing');

        $this->info("Building match dataset for date: {$date}");

        $query = Fixture::whereDate('fixture_date', $date)
            ->where('fas_status', 'ELIGIBLE');

        if ($fixtureId) {
            $query->where('id', $fixtureId);
        }

        $fixtures = $query->get();

        if ($fixtures->isEmpty()) {
            $this->warn('No eligible fixtures found.');

            return;
        }

        $this->info("Found {$fixtures->count()} eligible fixtures.");

        foreach ($fixtures as $fixture) {
            $this->info("Dispatching job for fixture ID: {$fixture->id}");
            BuildMatchDatasetJob::dispatch($fixture, $force, $syncMissing);
        }

        $this->info('Jobs dispatched successfully.');
    }
}
