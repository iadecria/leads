<?php

namespace Tests\Feature\Dataset;

use App\Models\Fixture;
use App\Models\Team;
use App\Services\Dataset\MatchDatasetBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataLeakageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dataset_does_not_leak_future_data()
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        // Past match 1
        Fixture::factory()->create([
            'home_team_id' => $homeTeam->id,
            'fixture_date' => Carbon::now()->subDays(10),
            'status' => 'FT',
            'home_score' => 2,
            'away_score' => 0,
        ]);

        // Past match 2
        Fixture::factory()->create([
            'away_team_id' => $awayTeam->id,
            'fixture_date' => Carbon::now()->subDays(5),
            'status' => 'FT',
            'home_score' => 1,
            'away_score' => 1,
        ]);

        // The target match to analyze
        $targetFixture = Fixture::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'fixture_date' => Carbon::now(),
            'status' => 'NS',
        ]);

        // Future match
        Fixture::factory()->create([
            'home_team_id' => $homeTeam->id,
            'fixture_date' => Carbon::now()->addDays(5),
            'status' => 'FT',
            'home_score' => 5,
            'away_score' => 0,
        ]);

        $builder = app(MatchDatasetBuilder::class);
        $record = $builder->build($targetFixture, true);

        // Assert future match is not included in the sample
        $payload = $record->payload;

        $homeMatchesSample = $payload['home_stats']['last_20']['form']['matches'] ?? 0;

        // Only 1 past match for home team, not 2
        $this->assertEquals(1, $homeMatchesSample);

        $this->assertEquals(2, $payload['home_stats']['last_20']['form']['goals_scored']);
    }
}
