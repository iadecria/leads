<?php

namespace App\Services\ApiFootball;

use App\Interfaces\Providers\FootballDataProviderInterface;

class ApiFootballProviderService implements FootballDataProviderInterface
{
    private ApiFootballClient $client;

    public function __construct(ApiFootballClient $client)
    {
        $this->client = $client;
    }

    public function getFixturesByDate(string $date): array
    {
        return $this->client->get('fixtures', ['date' => $date]);
    }

    public function getFixtureStatistics(int $fixtureId): array
    {
        return $this->client->get('fixtures/statistics', ['fixture' => $fixtureId]);
    }

    public function getPredictions(int $fixtureId): array
    {
        return $this->client->get('predictions', ['fixture' => $fixtureId]);
    }

    public function getLeagues(int $season): array
    {
        return $this->client->get('leagues', ['season' => $season]);
    }

    public function getTeams(int $leagueId, int $season): array
    {
        return $this->client->get('teams', ['league' => $leagueId, 'season' => $season]);
    }

    public function getStandings(int $leagueId, int $season): array
    {
        return $this->client->get('standings', ['league' => $leagueId, 'season' => $season]);
    }

    public function getFixtureEvents(int $fixtureId): array
    {
        return $this->client->get('fixtures/events', ['fixture' => $fixtureId]);
    }

    public function getFixtureLineups(int $fixtureId): array
    {
        return $this->client->get('fixtures/lineups', ['fixture' => $fixtureId]);
    }

    public function getHeadToHead(string $h2h): array
    {
        return $this->client->get('fixtures/headtohead', ['h2h' => $h2h]);
    }

    public function getInjuries(int $fixtureId): array
    {
        return $this->client->get('injuries', ['fixture' => $fixtureId]);
    }

    public function getOdds(int $fixtureId): array
    {
        return $this->client->get('odds', ['fixture' => $fixtureId]);
    }
}
