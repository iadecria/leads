<?php

namespace App\Services\OpenRouter;

use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Team;
use Carbon\Carbon;
use App\Services\Discovery\PublicFixtureDiscoveryService;

class OpenRouterResearchService
{
    public function __construct(
        private OpenRouterResearchProvider $provider,
        private PublicFixtureDiscoveryService $discoveryService
    )
    {
    }

    public function researchDate(string $date): array
    {
        $fixtures = Fixture::with(['competition', 'homeTeam', 'awayTeam'])
            ->whereDate('fixture_date', $date)
            ->orderBy('fixture_date')
            ->get();

        $results = [];

        foreach ($fixtures as $fixture) {
            $results[] = [
                'fixture_id' => $fixture->id,
                'home' => $fixture->homeTeam?->name ?? 'Home Team',
                'away' => $fixture->awayTeam?->name ?? 'Away Team',
                'data' => $this->provider->researchFixture($fixture, now()->toIso8601String()),
            ];
        }

        return $results;
    }

    public function discoverAndSyncFixtures(string $date): array
    {
        $fixtures = $this->discoveryService->discoverByDate($date);
        $payload = [
            'source' => config('openrouter.fixture_discovery_source', 'thesportsdb'),
            'fixtures' => $fixtures,
            'fallback' => false,
        ];

        if (empty($fixtures)) {
            $payload['fallback'] = true;
            $payload['fallback_source'] = 'openrouter';
            $fixtures = $this->provider->discoverFixturesByDate($date, now()->toIso8601String())['fixtures'] ?? [];
        }

        $synced = [];

        foreach ($fixtures as $item) {
            $competition = null;
            if (! empty($item['competition'])) {
                $competition = Competition::firstOrCreate(
                    ['external_id' => $item['league_id'] ?? crc32($item['competition'])],
                    [
                        'name' => $item['competition'],
                        'country' => $item['country'] ?? null,
                        'fas_enabled' => true,
                    ]
                );
            }

            $homeTeam = Team::firstOrCreate(
                ['external_id' => $item['home_team_id'] ?? crc32($item['home_team'])],
                ['name' => $item['home_team'], 'country' => $item['country'] ?? null]
            );
            $awayTeam = Team::firstOrCreate(
                ['external_id' => $item['away_team_id'] ?? crc32($item['away_team'])],
                ['name' => $item['away_team'], 'country' => $item['country'] ?? null]
            );

            $fixture = Fixture::updateOrCreate(
                [
                    'external_id' => $item['event_id'] ?? crc32(($item['home_team'] ?? '').'|'.($item['away_team'] ?? '').'|'.($item['date'] ?? $date)),
                ],
                [
                    'fixture_date' => Carbon::parse($item['datetime'] ?? ($item['date'] ?? $date))->toDateTimeString(),
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                    'competition_id' => $competition?->id,
                    'season' => $item['season'] ?? null,
                    'status' => 'NS',
                    'fas_status' => 'ELIGIBLE',
                    'fas_status_reason' => 'DISCOVERED_VIA_OPENROUTER',
                ]
            );

            $synced[] = [
                'fixture_id' => $fixture->id,
                'home_team' => $homeTeam->name,
                'away_team' => $awayTeam->name,
                'competition' => $competition?->name,
                'data' => $item,
            ];
        }

        return [
            'discovery' => $payload,
            'synced' => $synced,
        ];
    }

}
