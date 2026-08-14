<?php

namespace App\Services\Fas\Calculators;

class FasScoreCalculator
{
    /**
     * Calculates the FAS Score (0-100) which serves as the final recommendation grade.
     * High probability + High confidence = High FAS Score.
     */
    public function calculate(float $adjustedProbability, string $confidenceLevel, int $dataQualityScore, int $agreementScore): int
    {
        // 1. Probability component (Max 60)
        $probScore = $adjustedProbability * 60;

        // 2. Confidence/Quality component (Max 40)
        $confidenceMap = [
            'VERY_LOW' => 0.2,
            'LOW' => 0.4,
            'MEDIUM' => 0.6,
            'HIGH' => 0.8,
            'VERY_HIGH' => 1.0,
        ];

        $confidenceMultiplier = $confidenceMap[$confidenceLevel] ?? 0.5;

        $qualityFactor = ($dataQualityScore / 100);
        $agreementFactor = ($agreementScore / 100);

        // Composite multiplier (avg of the three, leaning towards confidence level)
        $compositeMultiplier = ($confidenceMultiplier * 0.5) + ($qualityFactor * 0.25) + ($agreementFactor * 0.25);

        $secondaryScore = 40 * $compositeMultiplier;

        $totalScore = $probScore + $secondaryScore;

        return (int) max(0, min(100, round($totalScore)));
    }
}
