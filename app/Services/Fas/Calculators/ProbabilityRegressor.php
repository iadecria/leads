<?php

namespace App\Services\Fas\Calculators;

class ProbabilityRegressor
{
    /**
     * Applies Bayesian/Beta-Binomial smoothing.
     * adjusted = (successes + prior_strength * prior) / (sample + prior_strength)
     */
    public function regress(float $successes, float $sample, float $prior, float $priorStrength): float
    {
        if ($sample === 0.0 && $priorStrength === 0.0) {
            return $prior;
        }

        return ($successes + ($priorStrength * $prior)) / ($sample + $priorStrength);
    }

    /**
     * Convenience method to calculate successes directly from a rate and sample size.
     */
    public function regressRate(float $rate, float $sample, float $prior, float $priorStrength): float
    {
        $successes = $rate * $sample;

        return $this->regress($successes, $sample, $prior, $priorStrength);
    }
}
