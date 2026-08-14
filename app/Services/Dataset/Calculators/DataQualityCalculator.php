<?php

namespace App\Services\Dataset\Calculators;

use App\Enums\DataQualityLevel;

class DataQualityCalculator
{
    public function calculate(array $metadata): array
    {
        $weights = config('fas.data_quality_weights'); // Can be used later for dynamic weights

        $score = 0;
        $breakdown = [];

        $historicalCount = $metadata['historical_fixtures'] ?? 0;
        $historyScore = min(30, ($historicalCount / 15) * 30);
        $score += $historyScore;
        $breakdown['history'] = $historyScore;

        $hasStats = $metadata['has_fixture_statistics'] ?? false;
        $statsScore = $hasStats ? 20 : 0;
        $score += $statsScore;
        $breakdown['statistics'] = $statsScore;

        $homeAwaySample = $metadata['home_away_sample'] ?? 0;
        $homeAwayScore = min(15, ($homeAwaySample / 8) * 15);
        $score += $homeAwayScore;
        $breakdown['home_away'] = $homeAwayScore;

        $hasStandings = $metadata['has_standings'] ?? false;
        $standingsScore = $hasStandings ? 10 : 0;
        $score += $standingsScore;
        $breakdown['standings'] = $standingsScore;

        $h2hCount = $metadata['head_to_head_count'] ?? 0;
        $h2hScore = min(5, ($h2hCount / 5) * 5);
        $score += $h2hScore;
        $breakdown['h2h'] = $h2hScore;

        $hasInjuries = $metadata['has_injuries'] ?? false;
        $injuriesScore = $hasInjuries ? 5 : 0;
        $score += $injuriesScore;
        $breakdown['injuries'] = $injuriesScore;

        $hasLineups = $metadata['has_lineups'] ?? false;
        $lineupsScore = $hasLineups ? 5 : 0;
        $score += $lineupsScore;
        $breakdown['lineups'] = $lineupsScore;

        $hasEvents = $metadata['has_events'] ?? false;
        $eventsScore = $hasEvents ? 5 : 0;
        $score += $eventsScore;
        $breakdown['events'] = $eventsScore;

        $coverageCompleteness = $metadata['coverage_completeness'] ?? 0;
        $coverageScore = $coverageCompleteness * 5;
        $score += $coverageScore;
        $breakdown['coverage'] = $coverageScore;

        $finalScore = (int) round($score);

        return [
            'score' => $finalScore,
            'level' => DataQualityLevel::fromScore($finalScore)->value,
            'breakdown' => $breakdown,
        ];
    }
}
