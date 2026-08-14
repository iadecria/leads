<?php

namespace App\Services\Dataset\Calculators;

use App\DTOs\Dataset\MetricValue;

class H2HCalculator
{
    public function calculate(array $h2hFixtures, int $homeTeamId, int $awayTeamId): array
    {
        $sampleSize = count($h2hFixtures);
        if ($sampleSize === 0) {
            return [
                'matches' => 0,
                'sample_size' => 0,
                'home_team_wins' => 0,
                'draws' => 0,
                'away_team_wins' => 0,
                'goals_avg' => new MetricValue(null, 0),
                'over15_rate' => new MetricValue(null, 0),
                'over25_rate' => new MetricValue(null, 0),
                'btts_rate' => new MetricValue(null, 0),
                'first_half_goal_rate' => new MetricValue(null, 0),
            ];
        }

        $homeWins = 0;
        $draws = 0;
        $awayWins = 0;
        $totalGoals = 0;
        $over15 = 0;
        $over25 = 0;
        $btts = 0;
        $fhGoalsCount = 0;
        $fhSample = 0;

        foreach ($h2hFixtures as $fixture) {
            $isHomeTeamA = $fixture->home_team_id === $homeTeamId;

            $teamAScore = $isHomeTeamA ? $fixture->home_score : $fixture->away_score;
            $teamBScore = $isHomeTeamA ? $fixture->away_score : $fixture->home_score;

            if ($teamAScore === null || $teamBScore === null) {
                continue;
            }

            if ($teamAScore > $teamBScore) {
                $homeWins++;
            } elseif ($teamAScore === $teamBScore) {
                $draws++;
            } else {
                $awayWins++;
            }

            $sum = $teamAScore + $teamBScore;
            $totalGoals += $sum;

            if ($sum > 1.5) {
                $over15++;
            }
            if ($sum > 2.5) {
                $over25++;
            }

            if ($teamAScore > 0 && $teamBScore > 0) {
                $btts++;
            }

            // First half goal calculation
            $eventsModel = $fixture->events->first();
            if ($eventsModel && $eventsModel->payload) {
                $fhSample++;
                $hasFhGoal = false;
                foreach ($eventsModel->payload as $event) {
                    if ($event['type'] === 'Goal' && isset($event['time']['elapsed']) && $event['time']['elapsed'] <= 45) {
                        $hasFhGoal = true;
                        break;
                    }
                }
                if ($hasFhGoal) {
                    $fhGoalsCount++;
                }
            }
        }

        return [
            'matches' => $sampleSize,
            'sample_size' => $sampleSize,
            'home_team_wins' => $homeWins,
            'draws' => $draws,
            'away_team_wins' => $awayWins,
            'goals_avg' => new MetricValue($totalGoals / $sampleSize, $sampleSize, $totalGoals),
            'over15_rate' => new MetricValue($over15 / $sampleSize, $sampleSize, $over15),
            'over25_rate' => new MetricValue($over25 / $sampleSize, $sampleSize, $over25),
            'btts_rate' => new MetricValue($btts / $sampleSize, $sampleSize, $btts),
            'first_half_goal_rate' => $fhSample > 0 ? new MetricValue($fhGoalsCount / $fhSample, $fhSample, $fhGoalsCount) : new MetricValue(null, 0),
        ];
    }
}
