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

class Over25Engine implements EventEngineInterface
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
        $minSample = config('fas.minimum_samples.over_2_5');
        $prior = config('fas.priors.over_2_5');
        $priorStrength = config('fas.prior_strengths.over_2_5');

        $components = [];
        $positiveFactors = [];
        $negativeFactors = [];
        $missingComponents = 0;

        $homeOverall = $dataset->homeStats->last10['goals']['over25_rate'] ?? null;
        $awayOverall = $dataset->awayStats->last10['goals']['over25_rate'] ?? null;

        $samples = [];
        if ($homeOverall && $homeOverall->sampleSize > 0) {
            $components['home_overall'] = $homeOverall->value;
            $samples[] = $homeOverall->sampleSize;
            $positiveFactors[] = 'Home overall O2.5 rate: '.round($homeOverall->value * 100).'%';
        } else {
            $missingComponents++;
            $negativeFactors[] = 'Home O2.5 goals data unavailable';
        }

        if ($awayOverall && $awayOverall->sampleSize > 0) {
            $components['away_overall'] = $awayOverall->value;
            $samples[] = $awayOverall->sampleSize;
            $positiveFactors[] = 'Away overall O2.5 rate: '.round($awayOverall->value * 100).'%';
        } else {
            $missingComponents++;
            $negativeFactors[] = 'Away O2.5 goals data unavailable';
        }

        $effectiveSample = $this->sampleCalculator->calculateEffectiveSample($samples);
        $sampleStrength = $this->sampleCalculator->classify($effectiveSample, $minSample);

        if ($sampleStrength === 'INSUFFICIENT' || count($components) === 0) {
            return [
                new EventPrediction(
                    event_type: 'OVER_2_5',
                    line: 2.5,
                    raw_probability: null,
                    adjusted_probability: null,
                    prior_probability: $prior,
                    effective_sample_size: $effectiveSample,
                    sample_strength: 'INSUFFICIENT',
                    data_quality_score: $dataset->dataQuality['score'],
                    data_quality_level: $dataset->dataQuality['level'],
                    confidence: 'VERY_LOW',
                    fas_score: 0,
                    positive_factors: $positiveFactors,
                    negative_factors: array_merge($negativeFactors, ['Insufficient sample size.']),
                    components: $components,
                    engine_version: config('fas.engine_version')
                ),
            ];
        }

        $rawProbability = array_sum($components) / count($components);
        $adjustedProbability = $this->regressor->regressRate($rawProbability, $effectiveSample, $prior, $priorStrength);

        $agreement = $this->agreementCalculator->calculate(array_values($components));
        $confidence = $this->confidenceCalculator->calculate($dataset->dataQuality['score'], $sampleStrength, $agreement, $missingComponents);
        $fasScore = $this->scoreCalculator->calculate($adjustedProbability, $confidence, $dataset->dataQuality['score'], $agreement);

        return [
            new EventPrediction(
                event_type: 'OVER_2_5',
                line: 2.5,
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
            ),
        ];
    }
}
