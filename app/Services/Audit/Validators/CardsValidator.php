<?php

namespace App\Services\Audit\Validators;

use App\Enums\FasAuditStatus;
use App\Models\FasEvent;
use App\Models\Fixture;

class CardsValidator implements EventValidatorInterface
{
    public function validate(FasEvent $event, Fixture $fixture): array
    {
        if (! $fixture->statistics()->exists()) {
            return [
                'status' => FasAuditStatus::UNAVAILABLE,
                'observed_value' => null,
                'rule' => "total_cards > {$event->line}",
            ];
        }

        $redCardWeight = config('fas.audit.red_card_weight', 1);

        $totalCards = 0;
        $homeCards = 0;
        $awayCards = 0;

        foreach ($fixture->statistics as $stat) {
            $yellows = $stat->yellow_cards ?? 0;
            $reds = $stat->red_cards ?? 0;
            $teamCards = $yellows + ($reds * $redCardWeight);

            $totalCards += $teamCards;

            if ($stat->team_id === $fixture->home_team_id) {
                $homeCards = $teamCards;
            } elseif ($stat->team_id === $fixture->away_team_id) {
                $awayCards = $teamCards;
            }
        }

        $isHit = $totalCards > $event->line;

        return [
            'status' => $isHit ? FasAuditStatus::HIT : FasAuditStatus::MISS,
            'observed_value' => $totalCards,
            'rule' => "total_cards > {$event->line} (red_weight={$redCardWeight})",
            'fixture_result' => [
                'home_cards' => $homeCards,
                'away_cards' => $awayCards,
            ],
        ];
    }
}
