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

class ResultEngine implements EventEngineInterface
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
        $minSample = config('fas.minimum_samples.result');
        $priorHome = config('fas.priors.home_win');
        $priorDraw = config('fas.priors.draw');
        $priorAway = config('fas.priors.away_win');
        $priorStrength = config('fas.prior_strengths.result');

        $componentsHome = [];
        $componentsDraw = [];
        $componentsAway = [];
        $samples = [];
        $positiveFactors = [];
        $negativeFactors = [];

        // Win rates
        $homeWinRate = $dataset->homeStats->last10['form']['win_rate'] ?? null;
        $awayWinRate = $dataset->awayStats->last10['form']['win_rate'] ?? null;
        $homeDrawRate = $dataset->homeStats->last10['form']['draw_rate'] ?? null;
        $awayDrawRate = $dataset->awayStats->last10['form']['draw_rate'] ?? null;
        $homeLossRate = $dataset->homeStats->last10['form']['loss_rate'] ?? null;
        $awayLossRate = $dataset->awayStats->last10['form']['loss_rate'] ?? null;

        if ($homeWinRate && $homeWinRate->sampleSize > 0 && $awayLossRate && $awayLossRate->sampleSize > 0) {
            $componentsHome['form'] = ($homeWinRate->value + $awayLossRate->value) / 2;
            $componentsDraw['form'] = ($homeDrawRate->value + $awayDrawRate->value) / 2;
            $componentsAway['form'] = ($homeLossRate->value + $awayWinRate->value) / 2;
            $samples[] = min($homeWinRate->sampleSize, $awayWinRate->sampleSize);
            $positiveFactors[] = 'Home win rate: '.round($homeWinRate->value * 100).'% | Away loss rate: '.round($awayLossRate->value * 100).'%';
        }

        $effectiveSample = $this->sampleCalculator->calculateEffectiveSample($samples);
        $sampleStrength = $this->sampleCalculator->classify($effectiveSample, $minSample);

        if ($sampleStrength === 'INSUFFICIENT' || empty($componentsHome)) {
            // Return null for all 3
            return $this->buildInsufficient($dataset, $effectiveSample, $priorHome, $priorDraw, $priorAway);
        }

        // Calculate Raw
        $rawHome = array_sum($componentsHome) / count($componentsHome);
        $rawDraw = array_sum($componentsDraw) / count($componentsDraw);
        $rawAway = array_sum($componentsAway) / count($componentsAway);

        // Normalize Raw to sum to 1
        $sumRaw = $rawHome + $rawDraw + $rawAway;
        if ($sumRaw > 0) {
            $rawHome /= $sumRaw;
            $rawDraw /= $sumRaw;
            $rawAway /= $sumRaw;
        }

        // Regress each
        $adjHome = $this->regressor->regressRate($rawHome, $effectiveSample, $priorHome, $priorStrength);
        $adjDraw = $this->regressor->regressRate($rawDraw, $effectiveSample, $priorDraw, $priorStrength);
        $adjAway = $this->regressor->regressRate($rawAway, $effectiveSample, $priorAway, $priorStrength);

        // Re-normalize Adjusted to sum to 1
        $sumAdj = $adjHome + $adjDraw + $adjAway;
        if ($sumAdj > 0) {
            $adjHome /= $sumAdj;
            $adjDraw /= $sumAdj;
            $adjAway /= $sumAdj;
        }

        $agreement = $this->agreementCalculator->calculate(array_values($componentsHome));
        $confidence = $this->confidenceCalculator->calculate($dataset->dataQuality['score'], $sampleStrength, $agreement, 0);

        return [
            new EventPrediction(
                event_type: 'HOME_WIN',
                line: null,
                raw_probability: round($rawHome, 4),
                adjusted_probability: round($adjHome, 4),
                prior_probability: $priorHome,
                effective_sample_size: $effectiveSample,
                sample_strength: $sampleStrength,
                data_quality_score: $dataset->dataQuality['score'],
                data_quality_level: $dataset->dataQuality['level'],
                confidence: $confidence,
                fas_score: $this->scoreCalculator->calculate($adjHome, $confidence, $dataset->dataQuality['score'], $agreement),
                positive_factors: $positiveFactors,
                negative_factors: $negativeFactors,
                components: $componentsHome
            ),
            new EventPrediction(
                event_type: 'DRAW',
                line: null,
                raw_probability: round($rawDraw, 4),
                adjusted_probability: round($adjDraw, 4),
                prior_probability: $priorDraw,
                effective_sample_size: $effectiveSample,
                sample_strength: $sampleStrength,
                data_quality_score: $dataset->dataQuality['score'],
                data_quality_level: $dataset->dataQuality['level'],
                confidence: $confidence,
                fas_score: $this->scoreCalculator->calculate($adjDraw, $confidence, $dataset->dataQuality['score'], $agreement),
                positive_factors: $positiveFactors,
                negative_factors: $negativeFactors,
                components: $componentsDraw
            ),
            new EventPrediction(
                event_type: 'AWAY_WIN',
                line: null,
                raw_probability: round($rawAway, 4),
                adjusted_probability: round($adjAway, 4),
                prior_probability: $priorAway,
                effective_sample_size: $effectiveSample,
                sample_strength: $sampleStrength,
                data_quality_score: $dataset->dataQuality['score'],
                data_quality_level: $dataset->dataQuality['level'],
                confidence: $confidence,
                fas_score: $this->scoreCalculator->calculate($adjAway, $confidence, $dataset->dataQuality['score'], $agreement),
                positive_factors: $positiveFactors,
                negative_factors: $negativeFactors,
                components: $componentsAway
            ),
        ];
    }

    private function buildInsufficient($dataset, $sample, $ph, $pd, $pa)
    {
        return [
            new EventPrediction('HOME_WIN', null, null, null, $ph, $sample, 'INSUFFICIENT', $dataset->dataQuality['score'], $dataset->dataQuality['level'], 'VERY_LOW', 0, [], ['Insufficient sample.']),
            new EventPrediction('DRAW', null, null, null, $pd, $sample, 'INSUFFICIENT', $dataset->dataQuality['score'], $dataset->dataQuality['level'], 'VERY_LOW', 0, [], ['Insufficient sample.']),
            new EventPrediction('AWAY_WIN', null, null, null, $pa, $sample, 'INSUFFICIENT', $dataset->dataQuality['score'], $dataset->dataQuality['level'], 'VERY_LOW', 0, [], ['Insufficient sample.']),
        ];
    }
}
