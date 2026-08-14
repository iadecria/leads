<?php

namespace Tests\Feature\Audit;

use App\Enums\FasAuditStatus;
use App\Enums\FasEventType;
use App\Models\FasEvent;
use App\Models\Fixture;
use App\Services\Audit\Validators\FirstHalfGoalValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstHalfValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ht_validation()
    {
        $validator = new FirstHalfGoalValidator;
        $fixture = Fixture::factory()->create([
            'halftime_home_score' => 1,
            'halftime_away_score' => 0,
        ]);
        $event = FasEvent::factory()->make(['event_type' => FasEventType::FIRST_HALF_GOAL]);

        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::HIT, $result['status']);

        $fixture->update(['halftime_home_score' => 0, 'halftime_away_score' => 0]);
        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::MISS, $result['status']);
    }

    public function test_fallback_to_events()
    {
        $validator = new FirstHalfGoalValidator;
        $fixture = Fixture::factory()->create([
            'halftime_home_score' => null,
            'halftime_away_score' => null,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $fixture->events()->create([
            'payload' => [
                [
                    'time' => ['elapsed' => 35],
                    'team' => ['id' => $fixture->home_team_id],
                    'type' => 'Goal',
                    'detail' => 'Normal Goal',
                ],
            ],
        ]);

        $event = FasEvent::factory()->make(['event_type' => FasEventType::FIRST_HALF_GOAL]);

        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::HIT, $result['status']);
        $this->assertEquals(1, $result['observed_value']);
    }

    public function test_unavailable_when_no_ht_and_no_events()
    {
        $validator = new FirstHalfGoalValidator;
        $fixture = Fixture::factory()->create([
            'halftime_home_score' => null,
            'halftime_away_score' => null,
        ]);
        $event = FasEvent::factory()->make(['event_type' => FasEventType::FIRST_HALF_GOAL]);

        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::UNAVAILABLE, $result['status']);
    }
}
