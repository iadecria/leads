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

class FirstHalfGoalEngine implements EventEngineInterface
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
        $minSample = config('fas.minimum_samples.first_half_goal');
        $prior = config('fas.priors.first_half_goal');
        $priorStrength = config('fas.prior_strengths.first_half_goal');

        $components = [];
        $positiveFactors = [];
        $negativeFactors = [];
        $missingComponents = 0;

        $homeFH = $dataset->homeStats->last10['first_half']['matches_with_goal_rate'] ?? null;
        $awayFH = $dataset->awayStats->last10['first_half']['matches_with_goal_rate'] ?? null;

        $samples = [];
        if ($homeFH && $homeFH->sampleSize > 0) {
            $components['home_fh'] = $homeFH->value;
            $samples[] = $homeFH->sampleSize;
            $positiveFactors[] = 'Home FH goal rate: '.round($homeFH->value * 100).'%';
        } else {
            $missingComponents++;
            $negativeFactors[] = 'Home FH goals data unavailable';
        }

        if ($awayFH && $awayFH->sampleSize > 0) {
            $components['away_fh'] = $awayFH->value;
            $samples[] = $awayFH->sampleSize;
            $positiveFactors[] = 'Away FH goal rate: '.round($awayFH->value * 100).'%';
        } else {
            $missingComponents++;
            $negativeFactors[] = 'Away FH goals data unavailable';
        }

        $effectiveSample = $this->sampleCalculator->calculateEffectiveSample($samples);
        $sampleStrength = $this->sampleCalculator->classify($effectiveSample, $minSample);

        if ($sampleStrength === 'INSUFFICIENT' || count($components) === 0) {
            return [
                new EventPrediction(
                    event_type: 'FIRST_HALF_GOAL',
                    line: 0.5,
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
                event_type: 'FIRST_HALF_GOAL',
                line: 0.5,
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
