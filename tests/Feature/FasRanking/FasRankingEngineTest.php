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

class FasRankingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_deduplication_keeps_only_one_event_per_fixture_in_top_tiers()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);
        $fixture = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->addHours(5),
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE',
        ]);

        $analysis = FasAnalysis::factory()->create([
            'fixture_id' => $fixture->id,
        ]);

        // Create 3 strong events for the SAME fixture
        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.80,
            'fas_score' => 85,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.80]),
        ]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::OVER_2_5,
            'estimated_probability' => 0.75,
            'fas_score' => 80,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.75]),
        ]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis->id,
            'event_type' => FasEventType::BTTS,
            'estimated_probability' => 0.78,
            'fas_score' => 82,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.78]),
        ]);

        $run = $engine->generate(now()->toDateString());

        // Only 1 should be in TOP3, the others should be in WATCHLIST due to deduplication
        $top3Count = $run->rankings()->where('ranking_type', 'TOP3')->count();
        $watchlistCount = $run->rankings()->where('ranking_type', 'WATCHLIST')->where('watchlist_reason', 'SECOND_EVENT_SAME_FIXTURE')->count();

        $this->assertEquals(1, $top3Count);
        $this->assertEquals(2, $watchlistCount);
    }
}
