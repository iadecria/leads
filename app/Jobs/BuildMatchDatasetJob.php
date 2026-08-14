<?php

namespace App\Jobs;

use App\Models\Fixture;
use App\Services\Dataset\MatchDatasetBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BuildMatchDatasetJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Fixture $fixture,
        public bool $force = false,
        public bool $syncMissing = false
    ) {}

    public function handle(MatchDatasetBuilder $builder): void
    {
        Log::info('dataset_started', ['fixture_id' => $this->fixture->id]);

        try {
            $record = $builder->build($this->fixture, $this->force);

            Log::info('dataset_completed', [
                'fixture_id' => $this->fixture->id,
                'dataset_version' => $record->dataset_version,
                'quality' => $record->data_quality_level,
            ]);
        } catch (\Exception $e) {
            Log::error('dataset_failed', [
                'fixture_id' => $this->fixture->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
