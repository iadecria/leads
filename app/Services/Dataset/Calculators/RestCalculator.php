<?php

namespace App\Services\Dataset\Calculators;

use Carbon\Carbon;

class RestCalculator
{
    public function calculate(Carbon $targetDate, array $homeFixtures, array $awayFixtures): array
    {
        $homeLast = collect($homeFixtures)->sortByDesc('fixture_date')->first();
        $awayLast = collect($awayFixtures)->sortByDesc('fixture_date')->first();

        $homeRest = $homeLast ? (int) $homeLast->fixture_date->diffInDays($targetDate) : null;
        $awayRest = $awayLast ? (int) $awayLast->fixture_date->diffInDays($targetDate) : null;

        $diff = null;
        if ($homeRest !== null && $awayRest !== null) {
            $diff = $homeRest - $awayRest;
        }

        return [
            'home_rest_days' => $homeRest,
            'away_rest_days' => $awayRest,
            'rest_difference' => $diff,
        ];
    }
}
