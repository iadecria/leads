<?php

namespace App\Services\Audit\Validators;

use App\Enums\FasAuditStatus;
use App\Models\FasEvent;
use App\Models\Fixture;

class OverGoalsValidator implements EventValidatorInterface
{
    public function validate(FasEvent $event, Fixture $fixture): array
    {
        if ($fixture->home_score === null || $fixture->away_score === null) {
            return [
                'status' => FasAuditStatus::UNAVAILABLE,
                'observed_value' => null,
                'rule' => "total_goals >= {$event->line}",
            ];
        }

        $totalGoals = $fixture->home_score + $fixture->away_score;
        $isHit = $totalGoals > $event->line; // e.g. total_goals > 1.5 -> means >= 2

        return [
            'status' => $isHit ? FasAuditStatus::HIT : FasAuditStatus::MISS,
            'observed_value' => $totalGoals,
            'rule' => "total_goals > {$event->line}",
            'fixture_result' => [
                'home' => $fixture->home_score,
                'away' => $fixture->away_score,
            ],
        ];
    }
}
