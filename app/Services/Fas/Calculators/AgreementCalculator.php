<?php

namespace App\Services\Fas\Calculators;

class AgreementCalculator
{
    /**
     * Calculates an agreement score (0-100) based on the variance of the components.
     * High variance = low agreement.
     * Low variance = high agreement.
     *
     * @param  float[]  $probabilities  List of probabilities to compare
     */
    public function calculate(array $probabilities): int
    {
        $count = count($probabilities);
        if ($count <= 1) {
            return 100; // Single component means perfect "agreement" with itself
        }

        $mean = array_sum($probabilities) / $count;

        $varianceSum = 0;
        foreach ($probabilities as $prob) {
            $varianceSum += pow($prob - $mean, 2);
        }
        $variance = $varianceSum / $count;
        $stdDev = sqrt($variance);

        // Max possible std dev for values between 0 and 1 is 0.5.
        // We map std dev 0 -> 100 score, std dev 0.25 -> ~50 score, std dev 0.5 -> 0 score.
        // Formula: 100 - (stdDev / 0.5) * 100
        $score = 100 - ($stdDev * 200);

        return (int) max(0, min(100, round($score)));
    }
}
