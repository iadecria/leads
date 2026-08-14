<?php

namespace Tests\Unit\FasEngine;

use App\Services\Fas\Calculators\ProbabilityRegressor;
use PHPUnit\Framework\TestCase;

class ProbabilityRegressorTest extends TestCase
{
    public function test_regress_pulls_towards_prior()
    {
        $regressor = new ProbabilityRegressor;

        $prior = 0.50;
        $strength = 5.0; // 5 matches worth of prior

        // 5 out of 5 observed (100%).
        // Formula: (5 + 5 * 0.5) / (5 + 5) = (5 + 2.5) / 10 = 0.75
        $adjusted = $regressor->regressRate(1.0, 5, $prior, $strength);

        $this->assertEquals(0.75, $adjusted);
    }

    public function test_larger_sample_suffers_less_regression()
    {
        $regressor = new ProbabilityRegressor;

        $prior = 0.50;
        $strength = 5.0;

        // 5 out of 5 observed (100%).
        $smallSample = $regressor->regressRate(1.0, 5, $prior, $strength);

        // 20 out of 20 observed (100%).
        $largeSample = $regressor->regressRate(1.0, 20, $prior, $strength);

        // 20/20 should be closer to 1.0 than 5/5
        $this->assertTrue($largeSample > $smallSample);
        $this->assertEquals(0.90, $largeSample);
    }
}
