<?php

namespace App\Services\Research;

use App\DTOs\Dataset\MetricValue;
use App\DTOs\Research\NormalizedResearchMatch;
use App\DTOs\ResearchFixtureResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ResearchDatasetAdapter
{
    public function __construct(
        private TeamNameResolver $teamNameResolver
    ) {
    }

    public function normalize(ResearchFixtureResult|array $result, string $homeTeam, string $awayTeam, string $cutoffAt): array
    {
        $payload = $result instanceof ResearchFixtureResult ? $result->toArray() : $result;
        $cutoff = Carbon::parse($cutoffAt)->utc();

        $homeMatches = $this->normalizeMatches($payload['home_recent_matches'] ?? [], $cutoff, $homeTeam, $awayTeam);
        $awayMatches = $this->normalizeMatches($payload['away_recent_matches'] ?? [], $cutoff, $homeTeam, $awayTeam);

        $homeHomeMatches = $this->normalizeMatches($payload['home_home_matches'] ?? [], $cutoff, $homeTeam, $awayTeam, true);
        $awayAwayMatches = $this->normalizeMatches($payload['away_away_matches'] ?? [], $cutoff, $homeTeam, $awayTeam, false);

        $homeStats = $this->buildTeamStats($homeMatches, $homeTeam, true, $homeHomeMatches);
        $awayStats = $this->buildTeamStats($awayMatches, $awayTeam, false, $awayAwayMatches);

        $homeRecent = $this->buildRecentSlices($homeMatches);
        $awayRecent = $this->buildRecentSlices($awayMatches);

        return [
            'home_recent_matches' => $homeMatches,
            'away_recent_matches' => $awayMatches,
            'home_home_matches' => $homeHomeMatches,
            'away_away_matches' => $awayAwayMatches,
            'home_stats' => $homeStats,
            'away_stats' => $awayStats,
            'trace' => [
                'cutoff_at' => $cutoff->toIso8601String(),
                'research_quality' => $payload['research_quality'] ?? 'INSUFFICIENT',
                'sources' => $payload['sources'] ?? [],
                'mode' => 'research_only',
                'normalized_counts' => [
                    'home_recent_matches' => $homeMatches->count(),
                    'away_recent_matches' => $awayMatches->count(),
                ],
            ],
        ];
    }

    public function buildDebugCounts(ResearchFixtureResult|array $result, string $homeTeam, string $awayTeam, string $cutoffAt): array
    {
        $payload = $result instanceof ResearchFixtureResult ? $result->toArray() : $result;
        $normalized = $this->normalize($payload, $homeTeam, $awayTeam, $cutoffAt);

        return [
            'research_matches_home' => count($payload['home_recent_matches'] ?? []),
            'research_matches_away' => count($payload['away_recent_matches'] ?? []),
            'normalized_home' => $normalized['home_recent_matches']->count(),
            'normalized_away' => $normalized['away_recent_matches']->count(),
            'dataset_home_last5' => $normalized['home_stats']->last5['goals']['over_15_rate']?->sampleSize ?? 0,
            'dataset_home_last10' => $normalized['home_stats']->last10['goals']['over_15_rate']?->sampleSize ?? 0,
            'dataset_away_last5' => $normalized['away_stats']->last5['goals']['over_15_rate']?->sampleSize ?? 0,
            'dataset_away_last10' => $normalized['away_stats']->last10['goals']['over_15_rate']?->sampleSize ?? 0,
        ];
    }

    private function normalizeMatches(array $matches, Carbon $cutoff, string $homeTeam, string $awayTeam, ?bool $teamIsHome = null): Collection
    {
        $seen = [];

        return collect($matches)
            ->map(fn (array $match) => $this->normalizeMatch($match))
            ->filter(fn (?NormalizedResearchMatch $match) => $match !== null)
            ->filter(function (NormalizedResearchMatch $match) use ($cutoff) {
                return Carbon::parse($match->date)->utc()->lt($cutoff) && $match->home_score_ft !== null && $match->away_score_ft !== null;
            })
            ->filter(function (NormalizedResearchMatch $match) use ($homeTeam, $awayTeam, $teamIsHome) {
                if ($teamIsHome === true) {
                    return $this->teamNameResolver->matches($match->home_team, $homeTeam);
                }

                if ($teamIsHome === false) {
                    return $this->teamNameResolver->matches($match->away_team, $awayTeam);
                }

                return $this->teamNameResolver->matches($match->home_team, $homeTeam)
                    || $this->teamNameResolver->matches($match->away_team, $awayTeam);
            })
            ->unique(fn (NormalizedResearchMatch $match) => $this->fingerprint($match))
            ->sortByDesc(fn (NormalizedResearchMatch $match) => Carbon::parse($match->date)->utc()->timestamp)
            ->values();
    }

    private function normalizeMatch(array $match): ?NormalizedResearchMatch
    {
        $homeScore = $match['home_score_ft'] ?? data_get($match, 'score.home');
        $awayScore = $match['away_score_ft'] ?? data_get($match, 'score.away');

        if ($homeScore === null || $awayScore === null) {
            return null;
        }

        $date = $match['date'] ?? $match['datetime'] ?? null;
        $homeTeam = $match['home_team'] ?? $match['home'] ?? null;
        $awayTeam = $match['away_team'] ?? $match['away'] ?? null;

        if (! $date || ! $homeTeam || ! $awayTeam) {
            return null;
        }

        return new NormalizedResearchMatch(
            date: Carbon::parse($date)->utc()->toIso8601String(),
            competition: $match['competition'] ?? null,
            home_team: $homeTeam,
            away_team: $awayTeam,
            home_score_ft: (int) $homeScore,
            away_score_ft: (int) $awayScore,
            home_score_ht: isset($match['home_score_ht']) ? (int) $match['home_score_ht'] : null,
            away_score_ht: isset($match['away_score_ht']) ? (int) $match['away_score_ht'] : null,
            corners_home: isset($match['corners_home']) ? (int) $match['corners_home'] : null,
            corners_away: isset($match['corners_away']) ? (int) $match['corners_away'] : null,
            cards_home: isset($match['cards_home']) ? (int) $match['cards_home'] : null,
            cards_away: isset($match['cards_away']) ? (int) $match['cards_away'] : null,
            shots_home: isset($match['shots_home']) ? (int) $match['shots_home'] : null,
            shots_away: isset($match['shots_away']) ? (int) $match['shots_away'] : null,
            shots_on_target_home: isset($match['shots_on_target_home']) ? (int) $match['shots_on_target_home'] : null,
            shots_on_target_away: isset($match['shots_on_target_away']) ? (int) $match['shots_on_target_away'] : null,
            possession_home: $match['possession_home'] ?? null,
            possession_away: $match['possession_away'] ?? null,
            source_urls: $match['source_urls'] ?? [],
            source_ids: $match['source_ids'] ?? [],
            confidence: $match['confidence'] ?? 'SINGLE_SOURCE_VALIDATED',
        );
    }

    private function fingerprint(NormalizedResearchMatch $match): string
    {
        return implode('|', [
            $match->date,
            $this->teamNameResolver->normalize($match->home_team),
            $this->teamNameResolver->normalize($match->away_team),
            $match->home_score_ft,
            $match->away_score_ft,
        ]);
    }

    private function buildTeamStats(Collection $matches, string $teamName, bool $isHomeSide, Collection $splitMatches): \App\DTOs\Dataset\TeamStats
    {
        $stats = new \App\DTOs\Dataset\TeamStats;
        $stats->last20 = $this->buildWindowStats($matches->take(20), $teamName);
        $stats->last10 = $this->buildWindowStats($matches->take(10), $teamName);
        $stats->last5 = $this->buildWindowStats($matches->take(5), $teamName);
        $stats->splitLast10 = $this->buildWindowStats($splitMatches->take(10), $teamName);
        $stats->splitLast5 = $this->buildWindowStats($splitMatches->take(5), $teamName);

        return $stats;
    }

    private function buildWindowStats(Collection $matches, string $teamName): array
    {
        $fixtures = $matches->map(function (NormalizedResearchMatch $match) use ($teamName) {
            return (object) [
                'home_team_id' => $this->teamNameResolver->matches($match->home_team, $teamName) ? 1 : 2,
                'away_team_id' => $this->teamNameResolver->matches($match->home_team, $teamName) ? 2 : 1,
                'home_score' => $match->home_score_ft,
                'away_score' => $match->away_score_ft,
                'statistics' => collect([]),
                'events' => collect([]),
            ];
        })->all();

        $calculator = app(\App\Services\Dataset\Calculators\StatsCalculator::class);

        return [
            'form' => $calculator->calculateForm($fixtures, 1),
            'goals' => $calculator->calculateGoals($fixtures),
            'first_half' => $calculator->calculateFirstHalf($fixtures, 1),
            'shots' => $calculator->calculateShots($fixtures, 1),
            'corners' => $calculator->calculateCorners($fixtures, 1),
            'cards' => $calculator->calculateCards($fixtures, 1),
            'possession' => $calculator->calculatePossession($fixtures, 1),
        ];
    }

    private function buildRecentSlices(Collection $matches): array
    {
        return [
            'last5' => $matches->take(5)->all(),
            'last10' => $matches->take(10)->all(),
            'last20' => $matches->take(20)->all(),
        ];
    }
}
