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

class RankingTieBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tie_breakers_determine_order_deterministically()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);

        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);

        // Two fixtures to put them in the same list and compete for ranks
        $fixture1 = Fixture::factory()->create(['competition_id' => $competition->id, 'fixture_date' => now()->addHours(5), 'status' => 'NS', 'fas_status' => 'ELIGIBLE', 'external_id' => 100]);
        $analysis1 = FasAnalysis::factory()->create(['fixture_id' => $fixture1->id]);

        $fixture2 = Fixture::factory()->create(['competition_id' => $competition->id, 'fixture_date' => now()->addHours(5), 'status' => 'NS', 'fas_status' => 'ELIGIBLE', 'external_id' => 200]);
        $analysis2 = FasAnalysis::factory()->create(['fixture_id' => $fixture2->id]);

        // Create two events with exactly identical scores to force tie breakers.
        // Event 1 has slightly higher data quality
        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis1->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.85,
            'fas_score' => 85,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 92, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.85]),
        ]);

        FasEvent::factory()->create([
            'fas_analysis_id' => $analysis2->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.85,
            'fas_score' => 85,
            'confidence' => ConfidenceLevel::HIGH,
            'payload' => json_encode(['data_quality_score' => 91, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.85]),
        ]);

        $run = $engine->generate(now()->toDateString());

        $top3 = $run->rankings()->where('ranking_type', 'TOP3')->orderBy('position')->get();

        // Fixture 1 should win because of Data Quality
        $this->assertEquals($fixture1->id, $top3[0]->event->analysis->fixture_id);
        $this->assertEquals($fixture2->id, $top3[1]->event->analysis->fixture_id);
    }
}
