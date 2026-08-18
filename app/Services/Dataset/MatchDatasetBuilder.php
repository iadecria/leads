<?php

namespace App\Services\Dataset;

use App\DTOs\Dataset\MatchDataset;
use App\DTOs\Dataset\TeamStats;
use App\DTOs\ResearchFixtureResult;
use App\Models\Fixture;
use App\Models\MatchDatasetRecord;
use App\Services\Dataset\Calculators\DataQualityCalculator;
use App\Services\Dataset\Calculators\H2HCalculator;
use App\Services\Dataset\Calculators\RestCalculator;
use App\Services\Dataset\Calculators\StatsCalculator;
use App\Services\Research\ResearchDatasetAdapter;
use Carbon\Carbon;

class MatchDatasetBuilder
{
    public function __construct(
        protected StatsCalculator $statsCalculator,
        protected DataQualityCalculator $qualityCalculator,
        protected H2HCalculator $h2hCalculator,
        protected RestCalculator $restCalculator,
        protected ResearchDatasetAdapter $researchDatasetAdapter
    ) {}

    public function buildFromResearch(Fixture $fixture, ResearchFixtureResult|array $researchResult, bool $force = false): MatchDatasetRecord
    {
        $cutoffAt = $fixture->fixture_date;
        $normalized = $this->researchDatasetAdapter->normalize(
            $researchResult,
            $fixture->homeTeam->name ?? '',
            $fixture->awayTeam->name ?? '',
            $cutoffAt->toIso8601String()
        );

        $dataset = new MatchDataset;
        $dataset->datasetVersion = config('fas.dataset_version');
        $dataset->fixture = $fixture->toArray();
        $dataset->homeTeam = $fixture->homeTeam->toArray();
        $dataset->awayTeam = $fixture->awayTeam->toArray();
        $dataset->homeStats = $normalized['home_stats'];
        $dataset->awayStats = $normalized['away_stats'];
        $dataset->headToHead = [];
        $dataset->rest = [];
        $dataset->standings = [];
        $dataset->injuries = [];
        $dataset->coverage = [];
        $dataset->dataQuality = $this->qualityCalculator->calculate([
            'historical_fixtures' => $normalized['home_recent_matches']->count() + $normalized['away_recent_matches']->count(),
            'home_away_sample' => $normalized['home_home_matches']->count() + $normalized['away_away_matches']->count(),
            'head_to_head_count' => 0,
            'has_fixture_statistics' => false,
            'has_events' => false,
            'has_standings' => false,
            'has_injuries' => false,
            'has_lineups' => false,
            'coverage_completeness' => 0,
        ]);
        $dataset->trace = array_merge($normalized['trace'], [
            'mode' => 'research_only',
            'home_recent_matches' => $normalized['home_recent_matches']->count(),
            'away_recent_matches' => $normalized['away_recent_matches']->count(),
        ]);

        $payload = [
            'fixture_id' => $fixture->id,
            'dataset_version' => $dataset->datasetVersion,
            'generated_at' => now(),
            'cutoff_at' => $cutoffAt,
            'data_quality_score' => $dataset->dataQuality['score'],
            'data_quality_level' => $dataset->dataQuality['level'],
            'payload' => $dataset->jsonSerialize(),
        ];

        return $force
            ? MatchDatasetRecord::create($payload)
            : MatchDatasetRecord::updateOrCreate(
                [
                    'fixture_id' => $fixture->id,
                    'dataset_version' => $dataset->datasetVersion,
                ],
                $payload
            );
    }

    public function build(Fixture $fixture, bool $force = false): MatchDatasetRecord
    {
        $cutoffAt = $fixture->fixture_date;

        if (! $force) {
            $existing = MatchDatasetRecord::where('fixture_id', $fixture->id)
                ->where('dataset_version', config('fas.dataset_version'))
                ->latest('generated_at')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Fetch Historical Fixtures (Eager load statistics and events)
        $homeFixtures = $this->getHistoricalFixtures($fixture->home_team_id, $cutoffAt, 20);
        $awayFixtures = $this->getHistoricalFixtures($fixture->away_team_id, $cutoffAt, 20);

        // Fetch Head-to-Head
        $h2hFixtures = $this->getHeadToHeadFixtures($fixture->home_team_id, $fixture->away_team_id, $cutoffAt, 10);

        $homeSplitFixtures = array_filter($homeFixtures, fn ($f) => $f->home_team_id === $fixture->home_team_id);
        $awaySplitFixtures = array_filter($awayFixtures, fn ($f) => $f->away_team_id === $fixture->away_team_id);

        $homeStats = new TeamStats;
        $homeStats->last20 = $this->calculateCombinedStats($homeFixtures, $fixture->home_team_id);
        $homeStats->last10 = $this->calculateCombinedStats(array_slice($homeFixtures, 0, 10), $fixture->home_team_id);
        $homeStats->last5 = $this->calculateCombinedStats(array_slice($homeFixtures, 0, 5), $fixture->home_team_id);

        $homeStats->splitLast10 = $this->calculateCombinedStats(array_slice($homeSplitFixtures, 0, 10), $fixture->home_team_id);
        $homeStats->splitLast5 = $this->calculateCombinedStats(array_slice($homeSplitFixtures, 0, 5), $fixture->home_team_id);

        $awayStats = new TeamStats;
        $awayStats->last20 = $this->calculateCombinedStats($awayFixtures, $fixture->away_team_id);
        $awayStats->last10 = $this->calculateCombinedStats(array_slice($awayFixtures, 0, 10), $fixture->away_team_id);
        $awayStats->last5 = $this->calculateCombinedStats(array_slice($awayFixtures, 0, 5), $fixture->away_team_id);

        $awayStats->splitLast10 = $this->calculateCombinedStats(array_slice($awaySplitFixtures, 0, 10), $fixture->away_team_id);
        $awayStats->splitLast5 = $this->calculateCombinedStats(array_slice($awaySplitFixtures, 0, 5), $fixture->away_team_id);

        $dataset = new MatchDataset;
        $dataset->datasetVersion = config('fas.dataset_version');
        $dataset->fixture = $fixture->toArray();
        $dataset->homeTeam = $fixture->homeTeam->toArray();
        $dataset->awayTeam = $fixture->awayTeam->toArray();
        $dataset->homeStats = $homeStats;
        $dataset->awayStats = $awayStats;

        $dataset->headToHead = $this->h2hCalculator->calculate($h2hFixtures, $fixture->home_team_id, $fixture->away_team_id);
        $dataset->rest = $this->restCalculator->calculate($fixture->fixture_date, $homeFixtures, $awayFixtures);

        // TODO: Standings and Injuries (to be implemented from API or database if available)
        $dataset->standings = [];
        $dataset->injuries = [];
        $dataset->coverage = [];

        // Data Quality
        $metadata = [
            'historical_fixtures' => count($homeFixtures) + count($awayFixtures),
            'home_away_sample' => count($homeSplitFixtures) + count($awaySplitFixtures),
            'head_to_head_count' => count($h2hFixtures),
            'has_fixture_statistics' => count(array_filter($homeFixtures, fn ($f) => $f->statistics && $f->statistics->isNotEmpty())) > 0,
            'has_events' => count(array_filter($homeFixtures, fn ($f) => $f->events)) > 0,
            // Mock other variables until we integrate fully
        ];

        $quality = $this->qualityCalculator->calculate($metadata);
        $dataset->dataQuality = $quality;

        // Trace
        $dataset->trace = [
            'cutoff_at' => $cutoffAt->toDateTimeString(),
            'dataset_version' => $dataset->datasetVersion,
            'home_fixtures_ids' => array_map(fn ($f) => $f->id, $homeFixtures),
            'away_fixtures_ids' => array_map(fn ($f) => $f->id, $awayFixtures),
            'h2h_fixtures_ids' => array_map(fn ($f) => $f->id, $h2hFixtures),
        ];

        // PERSIST SNAPSHOT IMMUTABILITY
        $payload = [
            'fixture_id' => $fixture->id,
            'dataset_version' => $dataset->datasetVersion,
            'generated_at' => now(),
            'cutoff_at' => $cutoffAt,
            'data_quality_score' => $quality['score'],
            'data_quality_level' => $quality['level'],
            'payload' => $dataset->jsonSerialize(),
        ];

        if ($force) {
            $record = MatchDatasetRecord::create($payload);
        } else {
            $record = MatchDatasetRecord::updateOrCreate(
                [
                    'fixture_id' => $fixture->id,
                    'dataset_version' => $dataset->datasetVersion,
                ],
                $payload
            );
        }

        return $record;
    }

    protected function getHistoricalFixtures(int $teamId, Carbon $cutoffAt, int $limit): array
    {
        return Fixture::with(['statistics', 'events'])
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->where('fixture_date', '<', $cutoffAt)
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->orderBy('fixture_date', 'desc')
            ->limit($limit)
            ->get()
            ->all();
    }

    protected function getHeadToHeadFixtures(int $homeTeamId, int $awayTeamId, Carbon $cutoffAt, int $limit): array
    {
        return Fixture::with(['events'])
            ->where(function ($q) use ($homeTeamId, $awayTeamId) {
                $q->where('home_team_id', $homeTeamId)->where('away_team_id', $awayTeamId)
                    ->orWhere('home_team_id', $awayTeamId)->where('away_team_id', $homeTeamId);
            })
            ->where('fixture_date', '<', $cutoffAt)
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->orderBy('fixture_date', 'desc')
            ->limit($limit)
            ->get()
            ->all();
    }

    protected function calculateCombinedStats(array $fixtures, int $teamId): array
    {
        return [
            'form' => $this->statsCalculator->calculateForm($fixtures, $teamId),
            'goals' => $this->statsCalculator->calculateGoals($fixtures),
            'first_half' => $this->statsCalculator->calculateFirstHalf($fixtures, $teamId),
            'shots' => $this->statsCalculator->calculateShots($fixtures, $teamId),
            'corners' => $this->statsCalculator->calculateCorners($fixtures, $teamId),
            'cards' => $this->statsCalculator->calculateCards($fixtures, $teamId),
            'possession' => $this->statsCalculator->calculatePossession($fixtures, $teamId),
        ];
    }
}
