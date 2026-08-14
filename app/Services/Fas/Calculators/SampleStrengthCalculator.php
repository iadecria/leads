<?php

namespace App\Services\Fas\Calculators;

class SampleStrengthCalculator
{
    /**
     * Calculates effective sample size from overlapping sample sizes (e.g. last 5, last 10, last 20)
     * We don't just sum them (which would be 35).
     * The largest sample represents the max distinct games.
     */
    public function calculateEffectiveSample(array $samples): float
    {
        if (empty($samples)) {
            return 0.0;
        }

        // Just take the max for now as conservative effective sample size,
        // or a slightly weighted sum. A simple and robust approach is MAX(samples).
        // Since last5 is fully contained in last10, which is fully contained in last20.
        // We'll return the max sample as the base effective sample.
        return (float) max($samples);
    }

    public function classify(float $effectiveSample, int $minimumSample): string
    {
        if ($effectiveSample < $minimumSample) {
            return 'INSUFFICIENT';
        }

        $ratio = $effectiveSample / max(1, $minimumSample);

        if ($ratio < 1.2) {
            return 'VERY_LOW';
        } elseif ($ratio < 1.5) {
            return 'LOW';
        } elseif ($ratio < 2.0) {
            return 'MEDIUM';
        } elseif ($ratio < 3.0) {
            return 'HIGH';
        }

        return 'VERY_HIGH';
    }
}
