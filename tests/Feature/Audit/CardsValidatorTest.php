<?php

namespace Tests\Feature\Audit;

use App\Enums\FasAuditStatus;
use App\Enums\FasEventType;
use App\Models\FasEvent;
use App\Models\Fixture;
use App\Services\Audit\Validators\CardsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CardsValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_validation_with_red_weight()
    {
        Config::set('fas.audit.red_card_weight', 2);

        $validator = new CardsValidator;
        $fixture = Fixture::factory()->create();

        $fixture->statistics()->create([
            'team_id' => $fixture->home_team_id,
            'yellow_cards' => 2,
            'red_cards' => 1,
            'raw_payload' => json_encode([]),
        ]);

        $fixture->statistics()->create([
            'team_id' => $fixture->away_team_id,
            'yellow_cards' => 3,
            'red_cards' => 0,
            'raw_payload' => json_encode([]),
        ]);

        $event = FasEvent::factory()->make(['event_type' => FasEventType::OVER_CARDS, 'line' => 4.5]);

        $result = $validator->validate($event, $fixture);

        // Home: 2 yellow + 1 red (weight 2) = 4
        // Away: 3 yellow + 0 red = 3
        // Total: 7 cards

        $this->assertEquals(FasAuditStatus::HIT, $result['status']);
        $this->assertEquals(7, $result['observed_value']);

        $event->line = 7.5;
        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::MISS, $result['status']);
    }

    public function test_unavailable_when_no_statistics()
    {
        $validator = new CardsValidator;
        $fixture = Fixture::factory()->create();
        $event = FasEvent::factory()->make(['event_type' => FasEventType::OVER_CARDS, 'line' => 4.5]);

        $result = $validator->validate($event, $fixture);
        $this->assertEquals(FasAuditStatus::UNAVAILABLE, $result['status']);
    }
}
