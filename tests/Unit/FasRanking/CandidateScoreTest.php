<?php

namespace Tests\Unit\FasRanking;

use App\Enums\FasEventType;
use App\Models\Competition;
use App\Models\FasAnalysis;
use App\Models\FasEvent;
use App\Models\Fixture;
use App\Services\Fas\Calculators\CandidateScoreCalculator;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CandidateScoreTest extends TestCase
{
    public function test_penalizes_low_sample_and_experimental_engine()
    {
        Config::set('fas.ranking.penalties.experimental_engine', 10);
        Config::set('fas.ranking.penalties.low_sample', 15);
        Config::set('fas.ranking.experimental_event_types', ['OVER_CORNERS']);

        $calculator = new CandidateScoreCalculator;

        $event = new FasEvent([
            'event_type' => FasEventType::OVER_CORNERS,
            'estimated_probability' => 0.80,
            'fas_score' => 80,
            'payload' => json_encode([
                'data_quality_score' => 80,
                'sample_strength' => 'LOW',
            ]),
        ]);

        $fixture = new Fixture;
        $fixture->setRelation('competition', new Competition(['fas_tier' => 'HIGH']));
        $analysis = new FasAnalysis;
        $analysis->setRelation('fixture', $fixture);
        $event->setRelation('analysis', $analysis);

        // Base score: (0.80 * 40) + (0.80 * 30) + (0.80 * 15) = 32 + 24 + 12 = 68
        // Penalties: 10 (experimental) + 15 (low sample) = 25
        // Expected: 68 - 25 = 43

        $result = $calculator->calculate($event);

        $this->assertEquals(43, $result['score']);
        $this->assertContains('EXPERIMENTAL_ENGINE', $result['penalties']);
        $this->assertContains('LOW_SAMPLE', $result['penalties']);
    }
}
