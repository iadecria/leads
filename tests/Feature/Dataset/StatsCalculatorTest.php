<?php

namespace Tests\Feature\Dataset;

use App\Models\Fixture;
use App\Models\FixtureStatistic;
use App\Models\Team;
use App\Services\Dataset\Calculators\StatsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_corners_calculator_ignores_nulls_for_sample_size()
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        // Match 1: has corners
        $fixture1 = Fixture::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'fixture_date' => Carbon::now()->subDays(10),
            'status' => 'FT',
            'home_score' => 2,
            'away_score' => 1,
        ]);

        FixtureStatistic::create([
            'fixture_id' => $fixture1->id,
            'team_id' => $homeTeam->id,
            'corners' => 5,
        ]);
        FixtureStatistic::create([
            'fixture_id' => $fixture1->id,
            'team_id' => $awayTeam->id,
            'corners' => 3,
        ]);

        // Match 2: NO corners (null)
        $fixture2 = Fixture::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'fixture_date' => Carbon::now()->subDays(5),
            'status' => 'FT',
            'home_score' => 0,
            'away_score' => 0,
        ]);

        FixtureStatistic::create([
            'fixture_id' => $fixture2->id,
            'team_id' => $homeTeam->id,
            'corners' => null, // null corners
        ]);
        FixtureStatistic::create([
            'fixture_id' => $fixture2->id,
            'team_id' => $awayTeam->id,
            'corners' => null, // null corners
        ]);

        $fixture1->load('statistics');
        $fixture2->load('statistics');

        $calculator = new StatsCalculator;
        $result = $calculator->calculateCorners([$fixture1, $fixture2], $homeTeam->id);

        // We passed 2 fixtures, but only 1 has corners
        $this->assertEquals(1, $result['corners_for_avg']->sampleSize);
        $this->assertEquals(5.0, $result['corners_for_avg']->value);
        $this->assertEquals(3.0, $result['corners_against_avg']->value);
        $this->assertEquals(8.0, $result['corners_total_avg']->value);
    }
}
