<?php

namespace Tests\Feature;

use App\Exceptions\ApiFootball\ApiFootballAuthenticationException;
use App\Exceptions\ApiFootball\ApiFootballRateLimitException;
use App\Models\Competition;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\ApiFootball\CompetitionSyncService;
use App\Services\ApiFootball\FixtureSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiFootballTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_client_handles_authentication_error()
    {
        Http::fake([
            '*' => Http::response(['errors' => ['token' => 'Error/Invalid']], 200),
        ]);

        $client = app(ApiFootballClient::class);

        $this->expectException(ApiFootballAuthenticationException::class);
        $client->get('leagues');
    }

    public function test_api_client_handles_rate_limit_error()
    {
        Http::fake([
            '*' => Http::response(['errors' => ['requests' => 'Limit reached']], 200),
        ]);

        $client = app(ApiFootballClient::class);

        $this->expectException(ApiFootballRateLimitException::class);
        $client->get('leagues');
    }

    public function test_competition_sync_service()
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    [
                        'league' => [
                            'id' => 39,
                            'name' => 'Premier League',
                            'type' => 'League',
                            'logo' => 'https://media.api-sports.io/football/leagues/39.png',
                        ],
                        'country' => ['name' => 'England'],
                        'seasons' => [
                            [
                                'year' => 2026,
                                'current' => true,
                                'coverage' => ['statistics_fixtures' => true, 'lineups' => true],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(CompetitionSyncService::class);
        $count = $service->sync(2026);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('competitions', [
            'external_id' => 39,
            'name' => 'Premier League',
        ]);
        $this->assertDatabaseHas('competition_seasons', [
            'season' => 2026,
            'is_current' => true,
        ]);
    }

    public function test_fixture_sync_service()
    {
        $competition = Competition::factory()->create([
            'external_id' => 39,
            'name' => 'Premier League',
            'country' => 'England',
            'fas_enabled' => true,
        ]);

        Http::fake([
            '*' => Http::response([
                'response' => [
                    [
                        'fixture' => [
                            'id' => 12345,
                            'date' => '2026-08-14T15:00:00+00:00',
                            'timezone' => 'UTC',
                            'venue' => ['name' => 'Wembley'],
                            'status' => ['short' => 'FT', 'elapsed' => 90],
                        ],
                        'league' => [
                            'id' => 39,
                            'season' => 2026,
                            'round' => 'Regular Season - 1',
                        ],
                        'teams' => [
                            'home' => ['id' => 33, 'name' => 'Manchester United', 'logo' => 'logo.png'],
                            'away' => ['id' => 34, 'name' => 'Chelsea', 'logo' => 'logo2.png'],
                        ],
                        'goals' => ['home' => 2, 'away' => 1],
                        'score' => ['halftime' => ['home' => 1, 'away' => 0]],
                    ],
                ],
            ], 200),
        ]);

        $service = app(FixtureSyncService::class);
        $count = $service->syncByDate('2026-08-14');

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('teams', ['external_id' => 33]);
        $this->assertDatabaseHas('fixtures', [
            'external_id' => 12345,
            'fas_status' => 'ELIGIBLE',
        ]);
    }
}
