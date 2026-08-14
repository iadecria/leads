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
use App\Services\Fas\Engines\ResultEngine;
use Tests\TestCase;

class ResultEngineTest extends TestCase
{
    public function test_probabilities_sum_to_one()
    {
        $engine = new ResultEngine(
            new ProbabilityRegressor,
            new SampleStrengthCalculator,
            new AgreementCalculator,
            new ConfidenceCalculator,
            new FasScoreCalculator
        );

        $dataset = new MatchDataset;
        $dataset->dataQuality = ['score' => 80, 'level' => 'HIGH'];

        $homeStats = new TeamStats;
        $homeStats->last10 = [
            'form' => [
                'win_rate' => new MetricValue(0.6, 10, 6),
                'draw_rate' => new MetricValue(0.2, 10, 2),
                'loss_rate' => new MetricValue(0.2, 10, 2),
            ],
        ];

        $awayStats = new TeamStats;
        $awayStats->last10 = [
            'form' => [
                'win_rate' => new MetricValue(0.3, 10, 3),
                'draw_rate' => new MetricValue(0.3, 10, 3),
                'loss_rate' => new MetricValue(0.4, 10, 4),
            ],
        ];

        $dataset->homeStats = $homeStats;
        $dataset->awayStats = $awayStats;

        $predictions = $engine->calculate($dataset);

        $this->assertCount(3, $predictions);

        $sum = 0;
        foreach ($predictions as $prediction) {
            $sum += $prediction->adjusted_probability;
        }

        $this->assertEqualsWithDelta(1.0, $sum, 0.001);
    }
}
