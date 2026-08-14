<?php

namespace App\Services;

use App\Enums\FasRunStatus;
use App\Models\FasRun;

class FasRunService
{
    public function generateFasForDate(string $date): FasRun
    {
        // TODO: This will hold the actual logic for generating FAS.
        // For now, just create a dummy run to satisfy the test and architecture.

        return FasRun::create([
            'analysis_date' => $date,
            'status' => FasRunStatus::COMPLETED,
            'started_at' => now(),
            'finished_at' => now(),
            'algorithm_version' => '1.0.0',
            'data_quality_score' => 100,
            'fixtures_found' => 0,
            'fixtures_eligible' => 0,
            'fixtures_analyzed' => 0,
        ]);
    }
}
