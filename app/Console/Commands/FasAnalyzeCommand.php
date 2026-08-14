<?php

namespace App\Console\Commands;

use App\Jobs\RunFasAnalysisJob;
use App\Models\Fixture;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FasAnalyzeCommand extends Command
{
    protected $signature = 'fas:analyze {date?} {--fixture=} {--force} {--build-missing-dataset}';

    protected $description = 'Run FAS Engine analysis for fixtures.';

    public function handle()
    {
        $dateStr = $this->argument('date') ?? Carbon::today()->toDateString();
        $fixtureId = $this->option('fixture');
        $force = $this->option('force');
        $buildDataset = $this->option('build-missing-dataset');

        $query = Fixture::query();

        if ($fixtureId) {
            $query->where('id', $fixtureId);
        } else {
            $query->whereDate('fixture_date', $dateStr)
                ->whereIn('status', ['NS', 'TBD']);
        }

        $fixtures = $query->get();

        if ($fixtures->isEmpty()) {
            $this->info('No eligible fixtures found.');

            return;
        }

        $this->info("Found {$fixtures->count()} fixtures to analyze.");

        $bar = $this->output->createProgressBar($fixtures->count());

        foreach ($fixtures as $fixture) {
            // We can dispatch synchronously or to queue. Since we want an interactive command, we dispatch sync.
            RunFasAnalysisJob::dispatchSync($fixture->id, $buildDataset || $force, null);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Analysis complete.');
    }
}
