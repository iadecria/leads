<?php

namespace Tests\Feature\Audit;

use App\Enums\FasAuditStatus;
use App\Enums\FasEventType;
use App\Models\FasEvent;
use App\Models\Fixture;
use App\Services\Audit\Validators\OverGoalsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverGoalsValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_over_1_5_validation()
    {
        $validator = new OverGoalsValidator;
        $fixture = Fixture::factory()->create(['home_score' => 1, 'away_score' => 1]);
        $event = FasEvent::factory()->make(['event_type' => FasEventType::OVER_1_5, 'line' => 1.5]);

        $result = $validator->validate($event, $fixture);

        $this->assertEquals(FasAuditStatus::HIT, $result['status']);
        $this->assertEquals(2, $result['observed_value']);

        // Test MISS
        $fixture->update(['home_score' => 1, 'away_score' => 0]);
        $result = $validator->validate($event, $fixture);

        $this->assertEquals(FasAuditStatus::MISS, $result['status']);
        $this->assertEquals(1, $result['observed_value']);
    }

    public function test_over_2_5_validation()
    {
        $validator = new OverGoalsValidator;
        $fixture = Fixture::factory()->create(['home_score' => 2, 'away_score' => 1]);
        $event = FasEvent::factory()->make(['event_type' => FasEventType::OVER_2_5, 'line' => 2.5]);

        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::HIT, $result['status']);

        $fixture->update(['home_score' => 2, 'away_score' => 0]);
        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::MISS, $result['status']);
    }

    public function test_unavailable_when_scores_are_null()
    {
        $validator = new OverGoalsValidator;
        $fixture = Fixture::factory()->create(['home_score' => null, 'away_score' => null]);
        $event = FasEvent::factory()->make(['event_type' => FasEventType::OVER_1_5, 'line' => 1.5]);

        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::UNAVAILABLE, $result['status']);
    }
}
