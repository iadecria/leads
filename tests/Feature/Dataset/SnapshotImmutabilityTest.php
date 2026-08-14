<?php

namespace Tests\Feature\Dataset;

use App\Models\Fixture;
use App\Models\MatchDatasetRecord;
use App\Models\Team;
use App\Services\Dataset\MatchDatasetBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_flag_creates_new_snapshot_preserving_old_one()
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $targetFixture = Fixture::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'fixture_date' => Carbon::now()->addDays(2),
            'status' => 'NS',
        ]);

        $builder = app(MatchDatasetBuilder::class);

        // Build once
        $record1 = $builder->build($targetFixture, false);

        // Advance time a bit to have different generated_at
        Carbon::setTestNow(Carbon::now()->addMinutes(5));

        // Build again without force
        $record2 = $builder->build($targetFixture, false);

        // Assert it's the exact same record ID
        $this->assertEquals($record1->id, $record2->id);

        // Build again WITH force
        $record3 = $builder->build($targetFixture, true);

        // Assert a new record was created
        $this->assertNotEquals($record1->id, $record3->id);
        $this->assertEquals($targetFixture->id, $record3->fixture_id);

        // Count should be 2 now
        $count = MatchDatasetRecord::where('fixture_id', $targetFixture->id)->count();
        $this->assertEquals(2, $count);
    }
}
