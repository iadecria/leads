<?php

namespace App\Services\Fas\Engines;

use App\DTOs\Dataset\MatchDataset;
use App\DTOs\Engine\EventPrediction;
use App\Interfaces\Engines\EventEngineInterface;
use App\Services\Fas\Calculators\AgreementCalculator;
use App\Services\Fas\Calculators\ConfidenceCalculator;
use App\Services\Fas\Calculators\FasScoreCalculator;
use App\Services\Fas\Calculators\ProbabilityRegressor;
use App\Services\Fas\Calculators\SampleStrengthCalculator;

class CornersEngine implements EventEngineInterface
{
    public function __construct(
        protected ProbabilityRegressor $regressor,
        protected SampleStrengthCalculator $sampleCalculator,
        protected AgreementCalculator $agreementCalculator,
        protected ConfidenceCalculator $confidenceCalculator,
        protected FasScoreCalculator $scoreCalculator
    ) {}

    public function calculate(MatchDataset $dataset): array
    {
        $minSample = config('fas.minimum_samples.corners');
        $prior = config('fas.priors.corners');
        $priorStrength = config('fas.prior_strengths.corners');

        $lines = [7.5, 8.5, 9.5, 10.5];
        $predictions = [];

        foreach ($lines as $line) {
            // Because we only stored averages and specific rates in the stats calculator,
            // we will need to calculate the probability of the line using Poisson or
            // if we had exact rates for 7.5, 8.5.
            // For V1, since we don't have Poisson yet, we will just use average corners to estimate.
            // If avg >= line + 0.5, raw = 0.6, etc.

            // To be accurate to the dataset, we should ideally have O8.5 rate in the DatasetBuilder.
            // But since the scope requested "Over 7.5, Over 8.5" and we only have "corners_total_avg",
            // we'll approximate or use INSUFFICIENT_DATA if we can't accurately say.
            // For now, let's build the structure.

            $homeAvg = $dataset->homeStats->last10['corners']['corners_total_avg'] ?? null;
            $awayAvg = $dataset->awayStats->last10['corners']['corners_total_avg'] ?? null;

            $samples = [];
            $components = [];
            $positiveFactors = [];
            $negativeFactors = [];

            if ($homeAvg && $homeAvg->sampleSize > 0) {
                $samples[] = $homeAvg->sampleSize;
                $components['home_avg'] = $homeAvg->value;
            }
            if ($awayAvg && $awayAvg->sampleSize > 0) {
                $samples[] = $awayAvg->sampleSize;
                $components['away_avg'] = $awayAvg->value;
            }

            $effectiveSample = $this->sampleCalculator->calculateEffectiveSample($samples);
            $sampleStrength = $this->sampleCalculator->classify($effectiveSample, $minSample);

            if ($sampleStrength === 'INSUFFICIENT' || count($components) === 0) {
                $predictions[] = new EventPrediction(
                    event_type: 'OVER_CORNERS',
                    line: $line,
                    raw_probability: null,
                    adjusted_probability: null,
                    prior_probability: $prior,
                    effective_sample_size: $effectiveSample,
                    sample_strength: 'INSUFFICIENT',
                    data_quality_score: $dataset->dataQuality['score'],
                    data_quality_level: $dataset->dataQuality['level'],
                    confidence: 'VERY_LOW',
                    fas_score: 0,
                    positive_factors: [],
                    negative_factors: ['Insufficient sample size or lacking precise over rate logic.'],
                    components: $components,
                    engine_version: config('fas.engine_version')
                );

                continue;
            }

            // Simple heuristic mapping for V1: if combined average > line + 1, probability is high.
            $combinedAvg = array_sum($components) / count($components);

            // This is a naive conversion from average to probability.
            // In a real scenario, we'd calculate exact rates for the line during DatasetBuilder.
            $diff = $combinedAvg - $line;
            $rawProbability = 0.5 + ($diff * 0.1);
            $rawProbability = max(0.01, min(0.99, $rawProbability));

            $positiveFactors[] = 'Combined corners average: '.round($combinedAvg, 2);

            $adjustedProbability = $this->regressor->regressRate($rawProbability, $effectiveSample, $prior, $priorStrength);
            $agreement = 100; // Hard to calculate agreement on a derived diff
            $confidence = $this->confidenceCalculator->calculate($dataset->dataQuality['score'], $sampleStrength, $agreement, 0);
            $fasScore = $this->scoreCalculator->calculate($adjustedProbability, $confidence, $dataset->dataQuality['score'], $agreement);

            $predictions[] = new EventPrediction(
                event_type: 'OVER_CORNERS',
                line: $line,
                raw_probability: round($rawProbability, 4),
                adjusted_probability: round($adjustedProbability, 4),
                prior_probability: $prior,
                effective_sample_size: $effectiveSample,
                sample_strength: $sampleStrength,
                data_quality_score: $dataset->dataQuality['score'],
                data_quality_level: $dataset->dataQuality['level'],
                confidence: $confidence,
                fas_score: $fasScore,
                positive_factors: $positiveFactors,
                negative_factors: $negativeFactors,
                components: $components,
                engine_version: config('fas.engine_version')
            );
        }

        return $predictions;
    }
}
