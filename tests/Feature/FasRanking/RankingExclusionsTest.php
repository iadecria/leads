<?php

namespace Tests\Feature\FasRanking;

use App\Enums\ConfidenceLevel;
use App\Enums\FasEventType;
use App\Models\Competition;
use App\Models\FasAnalysis;
use App\Models\FasEvent;
use App\Models\Fixture;
use App\Services\Fas\Calculators\CandidateScoreCalculator;
use App\Services\Fas\Engines\FasRankingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingExclusionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_experimental_events_are_watchlist_only()
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

        // Extremely strong corners
        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_CORNERS,
            'estimated_probability' => 0.95,
            'fas_score' => 98,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 100, 'sample_strength' => 'VERY_HIGH', 'adjusted_probability' => 0.95]),
        ]);

        $run = $engine->generate(now()->toDateString());

        $this->assertEquals(0, $run->rankings()->where('ranking_type', 'TOP3')->count());
        $this->assertEquals(1, $run->rankings()->where('ranking_type', 'WATCHLIST')->where('watchlist_reason', 'EXPERIMENTAL_ENGINE')->count());
    }

    public function test_cards_without_referee_is_excluded_from_top3()
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

        // Official cards event (if we move it from experimental), wait, config says OVER_CARDS is experimental right now.
        // Let's mock the config to make it official for this test.
        config(['fas.ranking.official_event_types' => ['OVER_CARDS']]);
        config(['fas.ranking.experimental_event_types' => []]);
        config(['fas.ranking.requirements.cards_requires_referee_for_top3' => true]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_CARDS,
            'estimated_probability' => 0.95,
            'fas_score' => 95,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.95]),
        ]);

        $run = $engine->generate(now()->toDateString());

        $this->assertEquals(0, $run->rankings()->where('ranking_type', 'TOP3')->count());
        $this->assertEquals(1, $run->rankings()->where('ranking_type', 'WATCHLIST')->where('watchlist_reason', 'MISSING_REFEREE')->count());
    }

    public function test_excluded_competitions()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        $competition = Competition::factory()->create(['name' => 'Friendly Match', 'type' => 'Friendly', 'fas_tier' => 'HIGH']);
        $fixture = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->addHours(5),
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE', // Imagine it slipped through fixture eligibility somehow
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

        $run = $engine->generate(now()->toDateString());

        $this->assertEquals(1, $run->rankings()->where('ranking_type', 'REJECTED')->where('watchlist_reason', 'COMPETITION_FRIENDLY')->count());
    }

    public function test_fixture_already_started()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);
        $fixture = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->subMinute(), // 1 minute ago
            'status' => '1H', // Started
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

        $run = $engine->generate(now()->toDateString());

        $this->assertEquals(1, $run->rankings()->where('ranking_type', 'REJECTED')->where('watchlist_reason', 'ALREADY_STARTED')->count());
    }
}
