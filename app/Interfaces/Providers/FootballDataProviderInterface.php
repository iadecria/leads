<?php

namespace App\Interfaces\Providers;

interface FootballDataProviderInterface
{
    /**
     * Fetch fixtures for a specific date
     */
    public function getFixturesByDate(string $date): array;

    /**
     * Get statistics for a specific fixture
     */
    public function getFixtureStatistics(int $fixtureId): array;

    /**
     * Get predictions/odds if available (for future use)
     */
    public function getPredictions(int $fixtureId): array;

    /**
     * Fetch all leagues/competitions for a given season
     */
    public function getLeagues(int $season): array;

    /**
     * Fetch teams for a specific league and season
     */
    public function getTeams(int $leagueId, int $season): array;

    /**
     * Fetch standings for a specific league and season
     */
    public function getStandings(int $leagueId, int $season): array;

    /**
     * Get events for a specific fixture
     */
    public function getFixtureEvents(int $fixtureId): array;

    /**
     * Get lineups for a specific fixture
     */
    public function getFixtureLineups(int $fixtureId): array;

    /**
     * Get head to head between two teams
     */
    public function getHeadToHead(string $h2h): array;

    /**
     * Get injuries for a specific fixture
     */
    public function getInjuries(int $fixtureId): array;

    /**
     * Get odds for a specific fixture
     */
    public function getOdds(int $fixtureId): array;
}
