<?php

namespace App\Services\ApiFootball;

use App\Interfaces\Providers\FootballDataProviderInterface;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FixtureSyncService
{
    private FootballDataProviderInterface $provider;

    public function __construct(FootballDataProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function syncByDate(string $date): int
    {
        $fixturesResponse = $this->provider->getFixturesByDate($date);
        $count = 0;

        foreach ($fixturesResponse as $item) {
            $fixtureData = $item['fixture'];
            $leagueData = $item['league'];
            $teamsData = $item['teams'];
            $goalsData = $item['goals'];
            $scoreData = $item['score'];

            // 1. Identify Competition
            $competition = Competition::where('external_id', $leagueData['id'])->first();

            if (! $competition) {
                // Not mapped yet, could auto-sync competition here or just skip.
                // For safety, let's create a disabled shell or just skip. Skipping for now.
                Log::warning("Skipped fixture {$fixtureData['id']} because competition {$leagueData['id']} is not synced.");

                continue;
            }

            // 2. Identify/Upsert Teams
            $homeTeam = Team::updateOrCreate(
                ['external_id' => $teamsData['home']['id']],
                [
                    'name' => $teamsData['home']['name'],
                    'logo' => $teamsData['home']['logo'],
                    'country' => $competition->country, // Guess country from league
                ]
            );

            $awayTeam = Team::updateOrCreate(
                ['external_id' => $teamsData['away']['id']],
                [
                    'name' => $teamsData['away']['name'],
                    'logo' => $teamsData['away']['logo'],
                    'country' => $competition->country,
                ]
            );

            // 3. Classify FAS Status (Basic Rules)
            $fasStatus = 'ELIGIBLE';
            $fasReason = null;

            if (! $competition->fas_enabled) {
                $fasStatus = 'EXCLUDED';
                $fasReason = 'COMPETITION_DISABLED';
            } elseif ($competition->name === 'Serie B') {
                $fasStatus = 'EXCLUDED';
                $fasReason = 'SERIE_B';
            } elseif (stripos($competition->name, 'Friendlies') !== false) {
                $fasStatus = 'EXCLUDED';
                $fasReason = 'FRIENDLY';
            } elseif (stripos($competition->name, 'Libertadores') !== false) {
                $fasStatus = 'EXCLUDED';
                $fasReason = 'LIBERTADORES';
            }

            // 4. Upsert Fixture
            Fixture::updateOrCreate(
                ['external_id' => $fixtureData['id']],
                [
                    'competition_id' => $competition->id,
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                    'season' => $leagueData['season'],
                    'round' => $leagueData['round'],
                    'fixture_date' => Carbon::parse($fixtureData['date'])->setTimezone('UTC'),
                    'timezone' => $fixtureData['timezone'],
                    'venue' => $fixtureData['venue']['name'] ?? null,
                    'status' => $fixtureData['status']['short'],
                    'elapsed' => $fixtureData['status']['elapsed'],
                    'home_score' => $goalsData['home'],
                    'away_score' => $goalsData['away'],
                    'halftime_home_score' => $scoreData['halftime']['home'] ?? null,
                    'halftime_away_score' => $scoreData['halftime']['away'] ?? null,
                    'fas_status' => $fasStatus,
                    'fas_status_reason' => $fasReason,
                ]
            );

            $count++;
        }

        return $count;
    }
}
