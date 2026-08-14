<?php

namespace Tests\Unit\FasEngine;

use App\Services\Fas\Calculators\AgreementCalculator;
use PHPUnit\Framework\TestCase;

class AgreementCalculatorTest extends TestCase
{
    public function test_perfect_agreement_yields_100()
    {
        $calculator = new AgreementCalculator;

        $score = $calculator->calculate([0.80, 0.80, 0.80]);
        $this->assertEquals(100, $score);
    }

    public function test_high_agreement_yields_high_score()
    {
        $calculator = new AgreementCalculator;

        // Very similar probabilities
        $score = $calculator->calculate([0.80, 0.78, 0.82, 0.75, 0.80]);

        $this->assertGreaterThan(90, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_low_agreement_yields_low_score()
    {
        $calculator = new AgreementCalculator;

        // High variance probabilities
        $score = $calculator->calculate([0.10, 0.90, 0.50]);

        $this->assertLessThan(60, $score);
    }
}
