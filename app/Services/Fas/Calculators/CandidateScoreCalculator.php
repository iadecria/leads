<?php

namespace App\Services\Fas\Calculators;

use App\Models\FasEvent;
use Illuminate\Support\Facades\Config;

class CandidateScoreCalculator
{
    /**
     * Calculates the Candidate Score (0-100) and returns an array with the score and penalties applied.
     */
    public function calculate(FasEvent $event): array
    {
        $payload = json_decode($event->payload, true);

        $adjustedProb = $event->estimated_probability ?? 0;
        $fasScore = $event->fas_score ?? 0;
        $dataQuality = $payload['data_quality_score'] ?? 0;

        // Agreement is inside components, but since we didn't store agreement_score explicitly
        // in FasEvent we can recalculate or just assume FAS Score already carries confidence/agreement weight.
        // For Candidate Score, the prompt says: "adjusted_probability, FAS Score, confidence, data_quality, sample_strength, agreement"
        // In V1, we can use the weights from config.

        $weights = Config::get('fas.ranking.candidate_score_weights', [
            'adjusted_probability' => 40,
            'fas_score' => 40,
            'data_quality' => 20,
        ]);

        $score = ($adjustedProb * $weights['adjusted_probability']);
        $score += ($fasScore / 100) * $weights['fas_score'];
        $score += ($dataQuality / 100) * $weights['data_quality'];

        $penaltiesConfig = Config::get('fas.ranking.penalties', []);
        $penaltiesApplied = [];

        // Apply penalties

        // Experimental Engine Penalty
        if (in_array($event->event_type->value, Config::get('fas.ranking.experimental_event_types', []))) {
            $score -= $penaltiesConfig['experimental_engine'] ?? 10;
            $penaltiesApplied[] = 'EXPERIMENTAL_ENGINE';
        }

        // Low Sample Penalty
        $sampleStrength = $payload['sample_strength'] ?? 'INSUFFICIENT';
        if ($sampleStrength === 'LOW' || $sampleStrength === 'VERY_LOW') {
            $score -= $penaltiesConfig['low_sample'] ?? 15;
            $penaltiesApplied[] = 'LOW_SAMPLE';
        } elseif ($sampleStrength === 'MEDIUM') {
            $score -= $penaltiesConfig['medium_sample'] ?? 5;
            $penaltiesApplied[] = 'MEDIUM_SAMPLE';
        }

        // Cards without referee
        if (str_contains($event->event_type->value, 'CARDS')) {
            // For V1 we assume referee is missing as per prompt
            $score -= $penaltiesConfig['cards_without_referee'] ?? 20;
            $penaltiesApplied[] = 'NO_REFEREE_DATA';
        }

        // Low Competition Tier (Requires fixture data, but we can do it via relationship)
        $fixture = $event->analysis->fixture;
        if ($fixture && $fixture->competition && $fixture->competition->fas_tier === 'LOW') {
            $score -= $penaltiesConfig['low_competition_tier'] ?? 10;
            $penaltiesApplied[] = 'LOW_COMPETITION_TIER';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => round($score, 2),
            'penalties' => $penaltiesApplied,
        ];
    }
}
