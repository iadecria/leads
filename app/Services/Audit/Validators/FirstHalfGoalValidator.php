<?php

namespace App\Services\Audit\Validators;

use App\Enums\FasAuditStatus;
use App\Models\FasEvent;
use App\Models\Fixture;

class FirstHalfGoalValidator implements EventValidatorInterface
{
    public function validate(FasEvent $event, Fixture $fixture): array
    {
        $htHome = $fixture->halftime_home_score;
        $htAway = $fixture->halftime_away_score;

        if ($htHome !== null && $htAway !== null) {
            $htGoals = $htHome + $htAway;
            $isHit = $htGoals >= 1;

            return [
                'status' => $isHit ? FasAuditStatus::HIT : FasAuditStatus::MISS,
                'observed_value' => $htGoals,
                'rule' => 'ht_total_goals >= 1',
                'fixture_result' => [
                    'halftime_home' => $htHome,
                    'halftime_away' => $htAway,
                ],
            ];
        }

        // Fallback: Attempt to use FixtureEvent payload if HT score is missing
        $fixtureEvent = $fixture->events()->first();
        if ($fixtureEvent && is_array($fixtureEvent->payload)) {
            $firstHalfGoals = 0;
            foreach ($fixtureEvent->payload as $evt) {
                if (($evt['type'] ?? '') === 'Goal') {
                    $time = $evt['time']['elapsed'] ?? 90;
                    if ($time <= 45) {
                        $firstHalfGoals++;
                    }
                }
            }

            // If we found any goals in the first half, it's a HIT
            if ($firstHalfGoals > 0) {
                return [
                    'status' => FasAuditStatus::HIT,
                    'observed_value' => $firstHalfGoals,
                    'rule' => 'ht_total_goals >= 1 (fallback: events)',
                    'fixture_result' => [
                        'first_half_goals_from_events' => $firstHalfGoals,
                    ],
                ];
            }
        }

        return [
            'status' => FasAuditStatus::UNAVAILABLE,
            'observed_value' => null,
            'rule' => 'ht_total_goals >= 1',
        ];
    }
}
