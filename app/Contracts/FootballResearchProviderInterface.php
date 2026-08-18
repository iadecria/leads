<?php

namespace App\Contracts;

use App\Models\Fixture;

interface FootballResearchProviderInterface
{
    public function researchFixture(Fixture $fixture, ?string $cutoffAt = null): array;

    public function researchTeamHistory(string $teamName, string $date, ?string $cutoffAt = null): array;

    public function researchMatchContext(string $homeTeam, string $awayTeam, string $date, ?string $cutoffAt = null): array;

    public function researchMissingMetrics(Fixture $fixture, array $missingFields, ?string $cutoffAt = null): array;
}
