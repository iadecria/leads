<?php

namespace App\Services\FootballData;

use App\Interfaces\Providers\FootballDataProviderInterface;

class ApiFootballProvider implements FootballDataProviderInterface
{
    public function getFixturesByDate(string $date): array
    {
        // TODO: Implement actual API integration later
        return [];
    }

    public function getFixtureStatistics(int $fixtureId): array
    {
        // TODO: Implement actual API integration later
        return [];
    }

    public function getPredictions(int $fixtureId): array
    {
        // TODO: Implement actual API integration later
        return [];
    }
}
