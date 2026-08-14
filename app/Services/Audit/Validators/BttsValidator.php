<?php

namespace App\Services\Audit\Validators;

use App\Enums\FasAuditStatus;
use App\Models\FasEvent;
use App\Models\Fixture;

class BttsValidator implements EventValidatorInterface
{
    public function validate(FasEvent $event, Fixture $fixture): array
    {
        if ($fixture->home_score === null || $fixture->away_score === null) {
            return [
                'status' => FasAuditStatus::UNAVAILABLE,
                'observed_value' => null,
                'rule' => 'home_score > 0 AND away_score > 0',
            ];
        }

        $isHit = ($fixture->home_score > 0) && ($fixture->away_score > 0);

        return [
            'status' => $isHit ? FasAuditStatus::HIT : FasAuditStatus::MISS,
            'observed_value' => $isHit ? 'YES' : 'NO',
            'rule' => 'home_score > 0 AND away_score > 0',
            'fixture_result' => [
                'home' => $fixture->home_score,
                'away' => $fixture->away_score,
            ],
        ];
    }
}
