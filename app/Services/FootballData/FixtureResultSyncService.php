<?php

namespace App\Services\FootballData;

use App\Interfaces\Providers\FootballDataProviderInterface;
use App\Models\Fixture;
use App\Models\FixtureStatistic;
use Illuminate\Support\Facades\Log;

class FixtureResultSyncService
{
    private FootballDataProviderInterface $provider;

    public function __construct(FootballDataProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Syncs post-match results for fixtures on a specific date.
     * Does not alter pre-match analysis data.
     */
    public function syncResultsForDate(string $date): void
    {
        $fixtures = Fixture::whereDate('fixture_date', $date)->get();
        if ($fixtures->isEmpty()) {
            return;
        }

        try {
            // Usually, getFixturesByDate fetches all games for that day, including their current status/score.
            $apiFixtures = $this->provider->getFixturesByDate($date);

            $apiDataMap = [];
            foreach ($apiFixtures as $apiFix) {
                $apiDataMap[$apiFix['fixture']['id']] = $apiFix;
            }

            foreach ($fixtures as $fixture) {
                if (! isset($apiDataMap[$fixture->external_id])) {
                    continue;
                }

                $apiFix = $apiDataMap[$fixture->external_id];

                // Update only allowed post-match data
                $fixture->update([
                    'status' => $apiFix['fixture']['status']['short'] ?? $fixture->status,
                    'home_score' => $apiFix['goals']['home'] ?? $fixture->home_score,
                    'away_score' => $apiFix['goals']['away'] ?? $fixture->away_score,
                    'halftime_home_score' => $apiFix['score']['halftime']['home'] ?? $fixture->halftime_home_score,
                    'halftime_away_score' => $apiFix['score']['halftime']['away'] ?? $fixture->halftime_away_score,
                ]);

                // If fixture is finished, attempt to fetch statistics if the competition/season provides coverage for it.
                // In a real scenario, this checks coverage. For now, we sync if it's finished.
                if (in_array($fixture->status, ['FT', 'AET', 'PEN'])) {
                    $this->syncStatisticsIfAvailable($fixture);
                }
            }
        } catch (\Exception $e) {
            Log::error("FixtureResultSyncService failed for date {$date}: ".$e->getMessage());
        }
    }

    private function syncStatisticsIfAvailable(Fixture $fixture): void
    {
        // Simple check: we don't need to re-sync if statistics already exist.
        if ($fixture->statistics()->exists()) {
            return;
        }

        try {
            $stats = $this->provider->getFixtureStatistics($fixture->external_id);

            foreach ($stats as $teamStats) {
                $teamId = ($teamStats['team']['id'] == $fixture->homeTeam->external_id)
                    ? $fixture->home_team_id
                    : ($teamStats['team']['id'] == $fixture->awayTeam->external_id ? $fixture->away_team_id : null);

                if (! $teamId) {
                    continue;
                }

                $mapped = $this->mapStatistics($teamStats['statistics']);

                FixtureStatistic::updateOrCreate(
                    [
                        'fixture_id' => $fixture->id,
                        'team_id' => $teamId,
                    ],
                    array_merge($mapped, [
                        'raw_payload' => $teamStats['statistics'],
                    ])
                );
            }
        } catch (\Exception $e) {
            Log::warning("Could not sync statistics for fixture {$fixture->id}: ".$e->getMessage());
        }
    }

    private function mapStatistics(array $stats): array
    {
        $mapped = [];
        foreach ($stats as $stat) {
            $type = $stat['type'];
            $value = $stat['value'] === null ? null : (int) $stat['value'];

            switch ($type) {
                case 'Shots on Goal': $mapped['shots_on_goal'] = $value;
                    break;
                case 'Shots off Goal': $mapped['shots_off_goal'] = $value;
                    break;
                case 'Total Shots': $mapped['shots_total'] = $value;
                    break;
                case 'Blocked Shots': $mapped['blocked_shots'] = $value;
                    break;
                case 'Shots insidebox': $mapped['shots_inside_box'] = $value;
                    break;
                case 'Shots outsidebox': $mapped['shots_outside_box'] = $value;
                    break;
                case 'Fouls': $mapped['fouls'] = $value;
                    break;
                case 'Corner Kicks': $mapped['corners'] = $value;
                    break;
                case 'Offsides': $mapped['offsides'] = $value;
                    break;
                case 'Yellow Cards': $mapped['yellow_cards'] = $value;
                    break;
                case 'Red Cards': $mapped['red_cards'] = $value;
                    break;
                case 'Ball Possession':
                    // Keep possession as string (e.g. "55%")
                    $mapped['possession'] = $stat['value'];
                    break;
            }
        }

        return $mapped;
    }
}
