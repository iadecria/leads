<?php

namespace App\Services\Dataset\Calculators;

use App\DTOs\Dataset\MetricValue;

class StatsCalculator
{
    public function calculateForm(array $fixtures, int $teamId): array
    {
        $sampleSize = count($fixtures);
        if ($sampleSize === 0) {
            return $this->emptyForm();
        }

        $wins = 0;
        $draws = 0;
        $losses = 0;
        $goalsScored = 0;
        $goalsConceded = 0;
        $cleanSheets = 0;
        $scoredIn = 0;
        $concededIn = 0;

        foreach ($fixtures as $fixture) {
            $isHome = $fixture->home_team_id === $teamId;
            $scored = $isHome ? $fixture->home_score : $fixture->away_score;
            $conceded = $isHome ? $fixture->away_score : $fixture->home_score;

            if ($scored === null || $conceded === null) {
                continue;
            }

            $goalsScored += $scored;
            $goalsConceded += $conceded;

            if ($scored > $conceded) {
                $wins++;
            } elseif ($scored === $conceded) {
                $draws++;
            } else {
                $losses++;
            }

            if ($conceded === 0) {
                $cleanSheets++;
            }
            if ($scored > 0) {
                $scoredIn++;
            }
            if ($conceded > 0) {
                $concededIn++;
            }
        }

        $points = ($wins * 3) + $draws;
        $possiblePoints = $sampleSize * 3;
        $winRate = $wins / $sampleSize;

        return [
            'matches' => $sampleSize,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'points' => $points,
            'possible_points' => $possiblePoints,
            'win_rate' => new MetricValue($winRate, $sampleSize, $wins),
            'goals_scored' => $goalsScored,
            'goals_conceded' => $goalsConceded,
            'avg_scored' => new MetricValue($goalsScored / $sampleSize, $sampleSize, $goalsScored),
            'avg_conceded' => new MetricValue($goalsConceded / $sampleSize, $sampleSize, $goalsConceded),
            'goal_difference' => $goalsScored - $goalsConceded,
            'clean_sheets' => $cleanSheets,
            'clean_sheet_rate' => new MetricValue($cleanSheets / $sampleSize, $sampleSize, $cleanSheets),
            'scored_in_rate' => new MetricValue($scoredIn / $sampleSize, $sampleSize, $scoredIn),
            'conceded_in_rate' => new MetricValue($concededIn / $sampleSize, $sampleSize, $concededIn),
        ];
    }

    public function calculateGoals(array $fixtures): array
    {
        $sampleSize = count($fixtures);
        if ($sampleSize === 0) {
            return $this->emptyGoals();
        }

        $totalGoals = 0;
        $over05 = 0;
        $over15 = 0;
        $over25 = 0;
        $over35 = 0;
        $btts = 0;

        foreach ($fixtures as $fixture) {
            $home = $fixture->home_score ?? 0;
            $away = $fixture->away_score ?? 0;
            $sum = $home + $away;

            $totalGoals += $sum;

            if ($sum > 0.5) {
                $over05++;
            }
            if ($sum > 1.5) {
                $over15++;
            }
            if ($sum > 2.5) {
                $over25++;
            }
            if ($sum > 3.5) {
                $over35++;
            }

            if ($home > 0 && $away > 0) {
                $btts++;
            }
        }

        return [
            'avg_total_goals' => new MetricValue($totalGoals / $sampleSize, $sampleSize, $totalGoals),
            'over_05_rate' => new MetricValue($over05 / $sampleSize, $sampleSize, $over05),
            'over_15_rate' => new MetricValue($over15 / $sampleSize, $sampleSize, $over15),
            'over_25_rate' => new MetricValue($over25 / $sampleSize, $sampleSize, $over25),
            'over_35_rate' => new MetricValue($over35 / $sampleSize, $sampleSize, $over35),
            'btts_rate' => new MetricValue($btts / $sampleSize, $sampleSize, $btts),
        ];
    }

    public function calculateFirstHalf(array $fixtures, int $teamId): array
    {
        $sampleSize = 0;
        $totalGoals = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;
        $scoredFirstHalfCount = 0;
        $concededFirstHalfCount = 0;
        $matchesWithGoalCount = 0;

        foreach ($fixtures as $fixture) {
            // First we need to get halftime score.
            // In API-Football, score.halftime is stored in the fixture. Let's assume we can parse it from analyses or we need to pass it.
            // Since we don't have halftime score directly in fixtures table (only home_score/away_score which are FT),
            // wait, we don't have halftime score in Fixture model. We might need it.
            // The prompt says "Se halftime score já resolver a métrica".
            // We should use `events` relation to count first half goals if halftime score is missing.

            $eventsModel = $fixture->events->first();
            $hasEvents = $eventsModel && $eventsModel->payload;

            if (! $hasEvents) {
                continue; // Can't calculate
            }

            $sampleSize++;

            $fhGoalsHome = 0;
            $fhGoalsAway = 0;

            foreach ($eventsModel->payload as $event) {
                if ($event['type'] === 'Goal' && isset($event['time']['elapsed']) && $event['time']['elapsed'] <= 45) {
                    if ($event['team']['id'] === $fixture->home_team_id) {
                        $fhGoalsHome++;
                    } else {
                        $fhGoalsAway++;
                    }
                }
            }

            $isHome = $fixture->home_team_id === $teamId;
            $scored = $isHome ? $fhGoalsHome : $fhGoalsAway;
            $conceded = $isHome ? $fhGoalsAway : $fhGoalsHome;
            $sum = $scored + $conceded;

            $totalGoals += $sum;
            $goalsFor += $scored;
            $goalsAgainst += $conceded;

            if ($scored > 0) {
                $scoredFirstHalfCount++;
            }
            if ($conceded > 0) {
                $concededFirstHalfCount++;
            }
            if ($sum > 0) {
                $matchesWithGoalCount++;
            }
        }

        if ($sampleSize === 0) {
            return [
                'first_half_goal_count' => new MetricValue(null, 0),
                'first_half_goal_rate' => new MetricValue(null, 0),
                'first_half_goals_for_avg' => new MetricValue(null, 0),
                'first_half_goals_against_avg' => new MetricValue(null, 0),
                'first_half_total_goals_avg' => new MetricValue(null, 0),
                'team_scored_first_half_rate' => new MetricValue(null, 0),
                'team_conceded_first_half_rate' => new MetricValue(null, 0),
            ];
        }

        return [
            'first_half_goal_count' => new MetricValue($matchesWithGoalCount, $sampleSize, $matchesWithGoalCount),
            'first_half_goal_rate' => new MetricValue($matchesWithGoalCount / $sampleSize, $sampleSize, $matchesWithGoalCount),
            'first_half_goals_for_avg' => new MetricValue($goalsFor / $sampleSize, $sampleSize, $goalsFor),
            'first_half_goals_against_avg' => new MetricValue($goalsAgainst / $sampleSize, $sampleSize, $goalsAgainst),
            'first_half_total_goals_avg' => new MetricValue($totalGoals / $sampleSize, $sampleSize, $totalGoals),
            'team_scored_first_half_rate' => new MetricValue($scoredFirstHalfCount / $sampleSize, $sampleSize, $scoredFirstHalfCount),
            'team_conceded_first_half_rate' => new MetricValue($concededFirstHalfCount / $sampleSize, $sampleSize, $concededFirstHalfCount),
        ];
    }

    public function calculateShots(array $fixtures, int $teamId): array
    {
        $sampleSize = 0;
        $totalFor = 0;
        $totalAgainst = 0;
        $onGoalFor = 0;
        $onGoalAgainst = 0;
        $insideFor = 0;
        $insideAgainst = 0;
        $blockedFor = 0;
        $blockedAgainst = 0;

        foreach ($fixtures as $fixture) {
            $stats = $fixture->statistics; // Collection of FixtureStatistic
            if (! $stats || $stats->isEmpty()) {
                continue;
            }

            $homeStats = $stats->firstWhere('team_id', $fixture->home_team_id);
            $awayStats = $stats->firstWhere('team_id', $fixture->away_team_id);

            if (! $homeStats || ! $awayStats || $homeStats->shots_total === null) {
                continue;
            }

            $sampleSize++;
            $isHome = $fixture->home_team_id === $teamId;

            $forStats = $isHome ? $homeStats : $awayStats;
            $againstStats = $isHome ? $awayStats : $homeStats;

            $totalFor += (int) $forStats->shots_total;
            $totalAgainst += (int) $againstStats->shots_total;
            $onGoalFor += (int) $forStats->shots_on_goal;
            $onGoalAgainst += (int) $againstStats->shots_on_goal;
            $insideFor += (int) $forStats->shots_inside_box;
            $insideAgainst += (int) $againstStats->shots_inside_box;
            $blockedFor += (int) $forStats->blocked_shots;
            $blockedAgainst += (int) $againstStats->blocked_shots;
        }

        if ($sampleSize === 0) {
            return [
                'shots_total_for_avg' => new MetricValue(null, 0),
                'shots_total_against_avg' => new MetricValue(null, 0),
                'shots_on_goal_for_avg' => new MetricValue(null, 0),
                'shots_on_goal_against_avg' => new MetricValue(null, 0),
                'shots_inside_box_for_avg' => new MetricValue(null, 0),
                'shots_inside_box_against_avg' => new MetricValue(null, 0),
                'blocked_shots_for_avg' => new MetricValue(null, 0),
                'blocked_shots_against_avg' => new MetricValue(null, 0),
            ];
        }

        return [
            'shots_total_for_avg' => new MetricValue($totalFor / $sampleSize, $sampleSize, $totalFor),
            'shots_total_against_avg' => new MetricValue($totalAgainst / $sampleSize, $sampleSize, $totalAgainst),
            'shots_on_goal_for_avg' => new MetricValue($onGoalFor / $sampleSize, $sampleSize, $onGoalFor),
            'shots_on_goal_against_avg' => new MetricValue($onGoalAgainst / $sampleSize, $sampleSize, $onGoalAgainst),
            'shots_inside_box_for_avg' => new MetricValue($insideFor / $sampleSize, $sampleSize, $insideFor),
            'shots_inside_box_against_avg' => new MetricValue($insideAgainst / $sampleSize, $sampleSize, $insideAgainst),
            'blocked_shots_for_avg' => new MetricValue($blockedFor / $sampleSize, $sampleSize, $blockedFor),
            'blocked_shots_against_avg' => new MetricValue($blockedAgainst / $sampleSize, $sampleSize, $blockedAgainst),
        ];
    }

    public function calculateCorners(array $fixtures, int $teamId): array
    {
        $sampleSize = 0;
        $cornersFor = 0;
        $cornersAgainst = 0;
        $totalCornersSum = 0;
        $over75 = 0;
        $over85 = 0;
        $over95 = 0;
        $over105 = 0;

        foreach ($fixtures as $fixture) {
            $stats = $fixture->statistics;
            if (! $stats || $stats->isEmpty()) {
                continue;
            }

            $homeStats = $stats->firstWhere('team_id', $fixture->home_team_id);
            $awayStats = $stats->firstWhere('team_id', $fixture->away_team_id);

            if (! $homeStats || ! $awayStats || $homeStats->corners === null) {
                continue;
            }

            $sampleSize++;
            $isHome = $fixture->home_team_id === $teamId;

            $for = $isHome ? (int) $homeStats->corners : (int) $awayStats->corners;
            $against = $isHome ? (int) $awayStats->corners : (int) $homeStats->corners;
            $sum = $for + $against;

            $cornersFor += $for;
            $cornersAgainst += $against;
            $totalCornersSum += $sum;

            if ($sum > 7.5) {
                $over75++;
            }
            if ($sum > 8.5) {
                $over85++;
            }
            if ($sum > 9.5) {
                $over95++;
            }
            if ($sum > 10.5) {
                $over105++;
            }
        }

        if ($sampleSize === 0) {
            return [
                'corners_for_avg' => new MetricValue(null, 0),
                'corners_against_avg' => new MetricValue(null, 0),
                'corners_total_avg' => new MetricValue(null, 0),
                'over75_corners_rate' => new MetricValue(null, 0),
                'over85_corners_rate' => new MetricValue(null, 0),
                'over95_corners_rate' => new MetricValue(null, 0),
                'over105_corners_rate' => new MetricValue(null, 0),
            ];
        }

        return [
            'corners_for_avg' => new MetricValue($cornersFor / $sampleSize, $sampleSize, $cornersFor),
            'corners_against_avg' => new MetricValue($cornersAgainst / $sampleSize, $sampleSize, $cornersAgainst),
            'corners_total_avg' => new MetricValue($totalCornersSum / $sampleSize, $sampleSize, $totalCornersSum),
            'over75_corners_rate' => new MetricValue($over75 / $sampleSize, $sampleSize, $over75),
            'over85_corners_rate' => new MetricValue($over85 / $sampleSize, $sampleSize, $over85),
            'over95_corners_rate' => new MetricValue($over95 / $sampleSize, $sampleSize, $over95),
            'over105_corners_rate' => new MetricValue($over105 / $sampleSize, $sampleSize, $over105),
        ];
    }

    public function calculateCards(array $fixtures, int $teamId): array
    {
        $sampleSize = 0;
        $yellowFor = 0;
        $yellowAgainst = 0;
        $redFor = 0;
        $redAgainst = 0;
        $totalCards = 0;
        $over25 = 0;
        $over35 = 0;
        $over45 = 0;
        $over55 = 0;
        $bothTeamsCard = 0;

        foreach ($fixtures as $fixture) {
            $stats = $fixture->statistics;
            if (! $stats || $stats->isEmpty()) {
                continue;
            }

            $homeStats = $stats->firstWhere('team_id', $fixture->home_team_id);
            $awayStats = $stats->firstWhere('team_id', $fixture->away_team_id);

            if (! $homeStats || ! $awayStats || $homeStats->yellow_cards === null) {
                continue;
            }

            $sampleSize++;
            $isHome = $fixture->home_team_id === $teamId;

            $yf = $isHome ? (int) $homeStats->yellow_cards : (int) $awayStats->yellow_cards;
            $ya = $isHome ? (int) $awayStats->yellow_cards : (int) $homeStats->yellow_cards;
            $rf = $isHome ? (int) $homeStats->red_cards : (int) $awayStats->red_cards;
            $ra = $isHome ? (int) $awayStats->red_cards : (int) $homeStats->red_cards;

            $sum = $yf + $ya + $rf + $ra;

            $yellowFor += $yf;
            $yellowAgainst += $ya;
            $redFor += $rf;
            $redAgainst += $ra;
            $totalCards += $sum;

            if ($sum > 2.5) {
                $over25++;
            }
            if ($sum > 3.5) {
                $over35++;
            }
            if ($sum > 4.5) {
                $over45++;
            }
            if ($sum > 5.5) {
                $over55++;
            }

            if (($yf + $rf > 0) && ($ya + $ra > 0)) {
                $bothTeamsCard++;
            }
        }

        if ($sampleSize === 0) {
            return [
                'yellow_cards_for_avg' => new MetricValue(null, 0),
                'yellow_cards_against_avg' => new MetricValue(null, 0),
                'red_cards_for_avg' => new MetricValue(null, 0),
                'red_cards_against_avg' => new MetricValue(null, 0),
                'cards_total_avg' => new MetricValue(null, 0),
                'over25_cards_rate' => new MetricValue(null, 0),
                'over35_cards_rate' => new MetricValue(null, 0),
                'over45_cards_rate' => new MetricValue(null, 0),
                'over55_cards_rate' => new MetricValue(null, 0),
                'both_teams_card_rate' => new MetricValue(null, 0),
            ];
        }

        return [
            'yellow_cards_for_avg' => new MetricValue($yellowFor / $sampleSize, $sampleSize, $yellowFor),
            'yellow_cards_against_avg' => new MetricValue($yellowAgainst / $sampleSize, $sampleSize, $yellowAgainst),
            'red_cards_for_avg' => new MetricValue($redFor / $sampleSize, $sampleSize, $redFor),
            'red_cards_against_avg' => new MetricValue($redAgainst / $sampleSize, $sampleSize, $redAgainst),
            'cards_total_avg' => new MetricValue($totalCards / $sampleSize, $sampleSize, $totalCards),
            'over25_cards_rate' => new MetricValue($over25 / $sampleSize, $sampleSize, $over25),
            'over35_cards_rate' => new MetricValue($over35 / $sampleSize, $sampleSize, $over35),
            'over45_cards_rate' => new MetricValue($over45 / $sampleSize, $sampleSize, $over45),
            'over55_cards_rate' => new MetricValue($over55 / $sampleSize, $sampleSize, $over55),
            'both_teams_card_rate' => new MetricValue($bothTeamsCard / $sampleSize, $sampleSize, $bothTeamsCard),
        ];
    }

    public function calculatePossession(array $fixtures, int $teamId): array
    {
        $sampleSize = 0;
        $possessionFor = 0;
        $possessionAgainst = 0;

        foreach ($fixtures as $fixture) {
            $stats = $fixture->statistics;
            if (! $stats || $stats->isEmpty()) {
                continue;
            }

            $homeStats = $stats->firstWhere('team_id', $fixture->home_team_id);
            $awayStats = $stats->firstWhere('team_id', $fixture->away_team_id);

            if (! $homeStats || ! $awayStats || empty($homeStats->possession)) {
                continue;
            }

            $sampleSize++;
            $isHome = $fixture->home_team_id === $teamId;

            $forStr = $isHome ? $homeStats->possession : $awayStats->possession;
            $againstStr = $isHome ? $awayStats->possession : $homeStats->possession;

            $for = (int) str_replace('%', '', $forStr);
            $against = (int) str_replace('%', '', $againstStr);

            $possessionFor += $for;
            $possessionAgainst += $against;
        }

        if ($sampleSize === 0) {
            return [
                'possession_for_avg' => new MetricValue(null, 0),
                'possession_against_avg' => new MetricValue(null, 0),
            ];
        }

        return [
            'possession_for_avg' => new MetricValue($possessionFor / $sampleSize, $sampleSize, $possessionFor),
            'possession_against_avg' => new MetricValue($possessionAgainst / $sampleSize, $sampleSize, $possessionAgainst),
        ];
    }

    private function emptyForm(): array
    {
        return [
            'matches' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'points' => 0,
            'possible_points' => 0,
            'win_rate' => new MetricValue(null, 0),
            'avg_scored' => new MetricValue(null, 0),
            'avg_conceded' => new MetricValue(null, 0),
            'clean_sheet_rate' => new MetricValue(null, 0),
            'scored_in_rate' => new MetricValue(null, 0),
            'conceded_in_rate' => new MetricValue(null, 0),
        ];
    }

    private function emptyGoals(): array
    {
        return [
            'avg_total_goals' => new MetricValue(null, 0),
            'over_05_rate' => new MetricValue(null, 0),
            'over_15_rate' => new MetricValue(null, 0),
            'over_25_rate' => new MetricValue(null, 0),
            'over_35_rate' => new MetricValue(null, 0),
            'btts_rate' => new MetricValue(null, 0),
        ];
    }
}
