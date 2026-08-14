<?php

namespace App\Services\Audit\Validators;

use App\Enums\FasAuditStatus;
use App\Enums\FasEventType;
use App\Models\FasEvent;
use App\Models\Fixture;

class ResultValidator implements EventValidatorInterface
{
    public function validate(FasEvent $event, Fixture $fixture): array
    {
        if ($fixture->home_score === null || $fixture->away_score === null) {
            return [
                'status' => FasAuditStatus::UNAVAILABLE,
                'observed_value' => null,
                'rule' => 'regular_time_score',
            ];
        }

        $homeScore = $fixture->home_score;
        $awayScore = $fixture->away_score;

        $isHit = false;
        $rule = '';

        if ($event->event_type === FasEventType::HOME_WIN) {
            $isHit = $homeScore > $awayScore;
            $rule = 'home_score > away_score';
        } elseif ($event->event_type === FasEventType::AWAY_WIN) {
            $isHit = $awayScore > $homeScore;
            $rule = 'away_score > home_score';
        } elseif ($event->event_type === FasEventType::DRAW) {
            $isHit = $homeScore === $awayScore;
            $rule = 'home_score == away_score';
        }

        return [
            'status' => $isHit ? FasAuditStatus::HIT : FasAuditStatus::MISS,
            'observed_value' => "{$homeScore}x{$awayScore}",
            'rule' => $rule,
            'fixture_result' => [
                'home' => $homeScore,
                'away' => $awayScore,
            ],
        ];
    }
}
