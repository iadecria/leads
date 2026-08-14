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

class RankingLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_top3_is_not_forced_to_three()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);
        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);

        // Create 2 fixtures with 1 great event each
        for ($i = 0; $i < 2; $i++) {
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
                'estimated_probability' => 0.80,
                'fas_score' => 85,
                'confidence' => ConfidenceLevel::HIGH,
                'payload' => json_encode(['data_quality_score' => 90, 'sample_strength' => 'HIGH', 'adjusted_probability' => 0.80]),
            ]);
        }

        // Add 1 terrible event that shouldn't make top3 or top5
        $fixtureBad = Fixture::factory()->create([
            'competition_id' => $competition->id,
            'fixture_date' => now()->addHours(5),
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE',
        ]);
        $analysisBad = FasAnalysis::factory()->create(['fixture_id' => $fixtureBad->id]);
        FasEvent::factory()->create([
            'fas_analysis_id' => $analysisBad->id,
            'event_type' => FasEventType::OVER_1_5,
            'estimated_probability' => 0.30,
            'fas_score' => 30,
            'confidence' => ConfidenceLevel::LOW,
            'payload' => json_encode(['data_quality_score' => 50, 'sample_strength' => 'LOW', 'adjusted_probability' => 0.30]),
        ]);

        $run = $engine->generate(now()->toDateString());

        // TOP 3 should have exactly 2
        $this->assertEquals(2, $run->rankings()->where('ranking_type', 'TOP3')->count());
        $this->assertEquals(0, $run->rankings()->where('ranking_type', 'TOP5')->count()); // Nobody qualified for top5 specifically
    }

    public function test_top5_is_not_forced()
    {
        $engine = new FasRankingEngine(new CandidateScoreCalculator);
        $competition = Competition::factory()->create(['fas_tier' => 'HIGH']);

        // Create 4 fixtures with TOP 5 eligible events (prob around 0.62)
        for ($i = 0; $i < 4; $i++) {
            $fixture = Fixture::factory()->create([
                'competition_id' => $competition->id,
                'fixture_date' => now()->addHours(5),
                'status' => 'NS',
                'fas_status' => 'ELIGIBLE',
            ]);
            $analysis = FasAnalysis::factory()->create(['fixture_id' => $fixture->id]);
            FasEvent::factory()->create([
                'fas_analysis_id' => $analysis->id,
                'event_type' => FasEventType::BTTS,
                'estimated_probability' => 0.62,
                'fas_score' => 65,
                'confidence' => ConfidenceLevel::MEDIUM, // TOP5 accepts MEDIUM
                'payload' => json_encode(['data_quality_score' => 65, 'sample_strength' => 'MEDIUM', 'adjusted_probability' => 0.62]),
            ]);
        }

        $run = $engine->generate(now()->toDateString());

        // TOP 3 should have 0
        $this->assertEquals(0, $run->rankings()->where('ranking_type', 'TOP3')->count());
        // TOP 5 should have exactly 4
        $this->assertEquals(4, $run->rankings()->where('ranking_type', 'TOP5')->count());
    }

    public function test_score_limits_between_0_and_100()
    {
        $calculator = new CandidateScoreCalculator;

        $event = new FasEvent([
            'event_type' => FasEventType::OVER_CORNERS,
            'estimated_probability' => -0.5,
            'fas_score' => -10,
            'payload' => json_encode([
                'data_quality_score' => 0,
                'sample_strength' => 'VERY_LOW',
            ]),
        ]);

        $fixture = new Fixture;
        $fixture->setRelation('competition', new Competition(['fas_tier' => 'LOW']));
        $analysis = new FasAnalysis;
        $analysis->setRelation('fixture', $fixture);
        $event->setRelation('analysis', $analysis);

        $result = $calculator->calculate($event);

        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }
}
