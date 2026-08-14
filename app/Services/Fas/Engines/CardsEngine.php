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

class CardsEngine implements EventEngineInterface
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
        $minSample = config('fas.minimum_samples.cards');
        $prior = config('fas.priors.cards');
        $priorStrength = config('fas.prior_strengths.cards');

        $lines = [2.5, 3.5, 4.5, 5.5];
        $predictions = [];

        foreach ($lines as $line) {
            $homeAvg = $dataset->homeStats->last10['cards']['cards_total_avg'] ?? null;
            $awayAvg = $dataset->awayStats->last10['cards']['cards_total_avg'] ?? null;

            $samples = [];
            $components = [];
            $positiveFactors = [];
            $negativeFactors = ['Referee adjustment = NOT_AVAILABLE'];

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
                    event_type: 'CARDS_OVER',
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
                    negative_factors: array_merge($negativeFactors, ['Insufficient sample size.']),
                    components: $components,
                    engine_version: config('fas.engine_version')
                );

                continue;
            }

            $combinedAvg = array_sum($components) / count($components);

            $diff = $combinedAvg - $line;
            $rawProbability = 0.5 + ($diff * 0.15);
            $rawProbability = max(0.01, min(0.99, $rawProbability));

            $positiveFactors[] = 'Combined cards average: '.round($combinedAvg, 2);

            $adjustedProbability = $this->regressor->regressRate($rawProbability, $effectiveSample, $prior, $priorStrength);
            $agreement = 100;
            $confidence = $this->confidenceCalculator->calculate($dataset->dataQuality['score'], $sampleStrength, $agreement, 1);
            $fasScore = $this->scoreCalculator->calculate($adjustedProbability, $confidence, $dataset->dataQuality['score'], $agreement);

            $predictions[] = new EventPrediction(
                event_type: 'CARDS_OVER',
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

        // Add BOTH_TEAMS_CARD (naive logic for V1)
        // ... (skipped for brevity, but follows same pattern)

        return $predictions;
    }
}
