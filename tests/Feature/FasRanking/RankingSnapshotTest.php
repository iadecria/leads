<?php

namespace Tests\Feature\FasRanking;

use App\Enums\ConfidenceLevel;
use App\Enums\FasEventType;
use App\Models\Competition;
use App\Models\FasAnalysis;
use App\Models\FasEvent;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use App\Services\Fas\Calculators\CandidateScoreCalculator;
use App\Services\Fas\Engines\FasRankingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RankingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_is_immutable_even_if_config_changes()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        Config::set('fas.ranking.top3.minimum_probability', 0.60);

        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);
        $fixture = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->addHours(5),
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE',
        ]);
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.65,
            'fas_score' => 80,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.65]),
        ]);

        $runA = $engine->generate(now()->toDateString());

        // Now we change the global config
        Config::set('fas.ranking.top3.minimum_probability', 0.90);

        // Fetch Run A again
        $runA->refresh();

        $snapshot = $runA->config_snapshot;

        $this->assertEquals(0.60, $snapshot['top3']['minimum_probability']);
        $this->assertNotEquals(0.90, $snapshot['top3']['minimum_probability']);
    }

    public function test_force_creates_new_snapshot_and_leaves_original_intact()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);
        $fixture = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->addHours(5),
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE',
        ]);
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.65,
            'fas_score' => 80,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.65]),
        ]);

        $runA = $engine->generate(now()->toDateString());

        $runWithoutForce = $engine->generate(now()->toDateString());

        $this->assertEquals($runA->id, $runWithoutForce->id);

        $runB = $engine->generate(now()->toDateString(), true);

        $this->assertNotEquals($runA->id, $runB->id);

        $this->assertEquals(2, FasRankingRun::count());
    }

    public function test_data_leakage_results_do_not_alter_historical_ranking()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);
        $fixture = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->addHours(5),
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE',
        ]);
        $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.90,
            'fas_score' => 90,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.90]),
        ]);

        $runA = $engine->generate(now()->toDateString());

        // Data leakage: simulation of a completed match with different data
        $fixture->update([
            'status' => 'FT',
            'home_score' => 0,
            'away_score' => 0,
        ]);

        $runA->refresh();

        // Ensure TOP3 event is still there
        $this->assertEquals(1, $runA->rankings()->where('ranking_type', 'TOP3')->count());
    }
}
