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

class Over15Engine implements EventEngineInterface
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
        $minSample = config('fas.minimum_samples.over_1_5');
        $prior = config('fas.priors.over_1_5');
        $priorStrength = config('fas.prior_strengths.over_1_5');

        $components = [];
        $positiveFactors = [];
        $negativeFactors = [];
        $missingComponents = 0;

        // Collect Rates
        $homeOverall = $dataset->homeStats->last10['goals']['over15_rate'] ?? null;
        $awayOverall = $dataset->awayStats->last10['goals']['over15_rate'] ?? null;
        $homeSplit = $dataset->homeStats->splitLast10['goals']['over15_rate'] ?? null;
        $awaySplit = $dataset->awayStats->splitLast10['goals']['over15_rate'] ?? null;

        $samples = [];
        if ($homeOverall && $homeOverall->sampleSize > 0) {
            $components['home_overall'] = $homeOverall->value;
            $samples[] = $homeOverall->sampleSize;
            $positiveFactors[] = 'Home overall O1.5 rate: '.round($homeOverall->value * 100)."% ({$homeOverall->count}/{$homeOverall->sampleSize})";
        } else {
            $missingComponents++;
            $negativeFactors[] = 'Home overall goals data unavailable';
        }

        if ($awayOverall && $awayOverall->sampleSize > 0) {
            $components['away_overall'] = $awayOverall->value;
            $samples[] = $awayOverall->sampleSize;
            $positiveFactors[] = 'Away overall O1.5 rate: '.round($awayOverall->value * 100)."% ({$awayOverall->count}/{$awayOverall->sampleSize})";
        } else {
            $missingComponents++;
            $negativeFactors[] = 'Away overall goals data unavailable';
        }

        if ($homeSplit && $homeSplit->sampleSize > 0) {
            $components['home_split'] = $homeSplit->value;
            $samples[] = $homeSplit->sampleSize;
        }

        if ($awaySplit && $awaySplit->sampleSize > 0) {
            $components['away_split'] = $awaySplit->value;
            $samples[] = $awaySplit->sampleSize;
        }

        $effectiveSample = $this->sampleCalculator->calculateEffectiveSample($samples);
        $sampleStrength = $this->sampleCalculator->classify($effectiveSample, $minSample);

        if ($sampleStrength === 'INSUFFICIENT' || count($components) === 0) {
            return [
                new EventPrediction(
                    event_type: 'OVER_1_5',
                    line: 1.5,
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

        // Calculate raw average of available components
        $rawProbability = array_sum($components) / count($components);

        // Regress
        $adjustedProbability = $this->regressRateWeighted($components, $effectiveSample, $prior, $priorStrength);

        $agreement = $this->agreementCalculator->calculate(array_values($components));

        $confidence = $this->confidenceCalculator->calculate(
            $dataset->dataQuality['score'],
            $sampleStrength,
            $agreement,
            $missingComponents
        );

        $fasScore = $this->scoreCalculator->calculate($adjustedProbability, $confidence, $dataset->dataQuality['score'], $agreement);

        return [
            new EventPrediction(
                event_type: 'OVER_1_5',
                line: 1.5,
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

    private function regressRateWeighted(array $components, float $sample, float $prior, float $priorStrength): float
    {
        $raw = array_sum($components) / count($components);

        return $this->regressor->regressRate($raw, $sample, $prior, $priorStrength);
    }
}
