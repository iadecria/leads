<?php

namespace App\Services\Fas\Calculators;

class ConfidenceCalculator
{
    public function calculate(int $dataQualityScore, string $sampleStrength, int $agreementScore, int $missingComponentsCount): string
    {
        $score = 0;

        // Base Data Quality (Max 40)
        $score += min(40, ($dataQualityScore / 100) * 40);

        // Sample Strength (Max 30)
        $sampleMap = [
            'INSUFFICIENT' => 0,
            'VERY_LOW' => 5,
            'LOW' => 10,
            'MEDIUM' => 20,
            'HIGH' => 25,
            'VERY_HIGH' => 30,
        ];
        $score += $sampleMap[$sampleStrength] ?? 0;

        // Agreement Score (Max 30)
        $score += min(30, ($agreementScore / 100) * 30);

        // Penalty for missing components
        $penalty = config('fas.ranking.penalties.missing_feature', 5);
        $score -= ($missingComponentsCount * $penalty);

        $score = max(0, $score);

        if ($score >= 80) {
            return 'VERY_HIGH';
        }
        if ($score >= 65) {
            return 'HIGH';
        }
        if ($score >= 50) {
            return 'MEDIUM';
        }
        if ($score >= 35) {
            return 'LOW';
        }

        return 'VERY_LOW';
    }
}
