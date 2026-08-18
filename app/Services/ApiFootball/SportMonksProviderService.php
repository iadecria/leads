<?php

namespace App\Services\ApiFootball;

use App\Interfaces\Providers\FootballDataProviderInterface;
use Illuminate\Support\Facades\Http;

class SportMonksProviderService implements FootballDataProviderInterface
{
    public function getFixturesByDate(string $date): array
    {
        $response = $this->request("fixtures/date/{$date}", [
            'include' => 'participants;league;scores;state',
        ]);

        return collect($response['data'] ?? [])->map(function (array $fixture) {
            $participants = $fixture['participants'] ?? [];
            $home = collect($participants)->first(function ($participant) {
                return data_get($participant, 'meta.location') === 'home';
            }) ?? [];
            $away = collect($participants)->first(function ($participant) {
                return data_get($participant, 'meta.location') === 'away';
            }) ?? [];

            return [
                'fixture' => [
                    'id' => $fixture['id'],
                    'date' => $fixture['starting_at'] ?? null,
                    'timezone' => 'UTC',
                    'status' => [
                        'short' => $this->mapState($fixture['state_id'] ?? null),
                        'elapsed' => null,
                    ],
                    'venue' => ['name' => null],
                ],
                'league' => [
                    'id' => $fixture['league_id'] ?? null,
                    'name' => $fixture['league']['name'] ?? null,
                    'country' => $fixture['league']['country']['name'] ?? null,
                    'season' => $fixture['season_id'] ?? null,
                    'round' => $fixture['name'] ?? null,
                ],
                'teams' => [
                    'home' => [
                        'id' => $home['id'] ?? null,
                        'name' => $home['name'] ?? null,
                        'logo' => $home['image_path'] ?? null,
                    ],
                    'away' => [
                        'id' => $away['id'] ?? null,
                        'name' => $away['name'] ?? null,
                        'logo' => $away['image_path'] ?? null,
                    ],
                ],
                'goals' => [
                    'home' => null,
                    'away' => null,
                ],
                'score' => [
                    'halftime' => [
                        'home' => null,
                        'away' => null,
                    ],
                ],
            ];
        })->all();
    }

    public function getFixtureStatistics(int $fixtureId): array
    {
        return [];
    }

    public function getPredictions(int $fixtureId): array
    {
        return [];
    }

    public function getLeagues(int $season): array
    {
        $response = $this->request('leagues', [
            'include' => 'country;seasons',
        ]);

        return collect($response['data'] ?? [])->map(function (array $league) {
            $season = collect($league['seasons'] ?? [])->first();

            return [
                'league' => [
                    'id' => $league['id'],
                    'name' => $league['name'] ?? null,
                    'type' => $league['type'] ?? null,
                    'logo' => $league['image_path'] ?? null,
                ],
                'country' => [
                    'name' => $league['country']['name'] ?? null,
                ],
                'seasons' => [
                    [
                        'year' => $season['year'] ?? $season['id'] ?? $season['name'] ?? null,
                        'current' => (bool) ($season['is_current'] ?? false),
                        'coverage' => $season['coverage'] ?? [],
                    ],
                ],
            ];
        })->all();
    }

    public function getTeams(int $leagueId, int $season): array
    {
        return [];
    }

    public function getStandings(int $leagueId, int $season): array
    {
        return [];
    }

    public function getFixtureEvents(int $fixtureId): array
    {
        return [];
    }

    public function getFixtureLineups(int $fixtureId): array
    {
        return [];
    }

    public function getHeadToHead(string $h2h): array
    {
        return [];
    }

    public function getInjuries(int $fixtureId): array
    {
        return [];
    }

    public function getOdds(int $fixtureId): array
    {
        return [];
    }

    private function request(string $endpoint, array $parameters = []): array
    {
        $token = config('api-football.sportmonks.key');
        $baseUrl = rtrim(config('api-football.sportmonks.base_url'), '/');

        $response = Http::timeout(config('api-football.timeout', 15))
            ->acceptJson()
            ->get("{$baseUrl}/{$endpoint}", array_merge($parameters, ['api_token' => $token]));

        $response->throw();

        return $response->json();
    }

    private function mapState(mixed $stateId): string
    {
        return match ((int) $stateId) {
            1, 2, 3 => 'NS',
            5 => 'FT',
            6 => 'AET',
            7 => 'PEN',
            default => 'NS',
        };
    }
}
