<?php

namespace Tests\Feature\FasEngine;

use App\DTOs\Dataset\MatchDataset;
use App\DTOs\Dataset\MetricValue;
use App\DTOs\Dataset\TeamStats;
use App\Services\Fas\Calculators\AgreementCalculator;
use App\Services\Fas\Calculators\ConfidenceCalculator;
use App\Services\Fas\Calculators\FasScoreCalculator;
use App\Services\Fas\Calculators\ProbabilityRegressor;
use App\Services\Fas\Calculators\SampleStrengthCalculator;
use App\Services\Fas\Engines\CornersEngine;
use Tests\TestCase;

class CornersEngineTest extends TestCase
{
    public function test_insufficient_sample_returns_no_adjusted_probability()
    {
        $engine = new CornersEngine(
            new ProbabilityRegressor,
            new SampleStrengthCalculator,
            new AgreementCalculator,
            new ConfidenceCalculator,
            new FasScoreCalculator
        );

        $dataset = new MatchDataset;
        $dataset->dataQuality = ['score' => 80, 'level' => 'HIGH'];

        $homeStats = new TeamStats;
        // Only 3 matches in sample, but config min is 10
        $homeStats->last10 = [
            'corners' => [
                'corners_total_avg' => new MetricValue(8.5, 3, 25.5),
            ],
        ];

        $awayStats = new TeamStats;
        $awayStats->last10 = [
            'corners' => [
                'corners_total_avg' => new MetricValue(9.5, 3, 28.5),
            ],
        ];

        $dataset->homeStats = $homeStats;
        $dataset->awayStats = $awayStats;

        $predictions = $engine->calculate($dataset);

        // We expect predictions for each line, but all should have INSUFFICIENT
        $this->assertCount(4, $predictions); // 7.5, 8.5, 9.5, 10.5

        foreach ($predictions as $prediction) {
            $this->assertNull($prediction->adjusted_probability);
            $this->assertEquals('INSUFFICIENT', $prediction->sample_strength);
            $this->assertEquals('VERY_LOW', $prediction->confidence);
        }
    }
}
