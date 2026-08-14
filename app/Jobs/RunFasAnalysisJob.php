<?php

namespace App\Jobs;

use App\DTOs\Dataset\MatchDataset;
use App\Models\Fixture;
use App\Services\Dataset\MatchDatasetBuilder;
use App\Services\Fas\FasEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunFasAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $fixtureId,
        public bool $forceBuildDataset = false,
        public ?int $runId = null
    ) {}

    public function handle(MatchDatasetBuilder $datasetBuilder, FasEngine $fasEngine): void
    {
        $fixture = Fixture::findOrFail($this->fixtureId);

        // Optional: skip if status is FT or if analysis already exists and we are not forcing.

        $datasetRecord = $datasetBuilder->build($fixture, $this->forceBuildDataset);
        $dataset = MatchDataset::fromArray($datasetRecord->payload);

        $fasEngine->analyze($dataset, $this->runId);
    }
}
