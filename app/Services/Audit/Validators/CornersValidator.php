<?php

namespace App\Services\Audit\Validators;

use App\Enums\FasAuditStatus;
use App\Models\FasEvent;
use App\Models\Fixture;

class CornersValidator implements EventValidatorInterface
{
    public function validate(FasEvent $event, Fixture $fixture): array
    {
        if (! $fixture->statistics()->exists()) {
            return [
                'status' => FasAuditStatus::UNAVAILABLE,
                'observed_value' => null,
                'rule' => "total_corners > {$event->line}",
            ];
        }

        $totalCorners = 0;
        $homeCorners = 0;
        $awayCorners = 0;

        foreach ($fixture->statistics as $stat) {
            $corners = $stat->corners ?? 0;
            $totalCorners += $corners;

            if ($stat->team_id === $fixture->home_team_id) {
                $homeCorners = $corners;
            } elseif ($stat->team_id === $fixture->away_team_id) {
                $awayCorners = $corners;
            }
        }

        $isHit = $totalCorners > $event->line;

        return [
            'status' => $isHit ? FasAuditStatus::HIT : FasAuditStatus::MISS,
            'observed_value' => $totalCorners,
            'rule' => "total_corners > {$event->line}",
            'fixture_result' => [
                'home_corners' => $homeCorners,
                'away_corners' => $awayCorners,
            ],
        ];
    }
}
