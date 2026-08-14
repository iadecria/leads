<?php

namespace Database\Factories;

use App\Enums\ConfidenceLevel;
use App\Enums\FasEventType;
use App\Models\FasAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

class FasEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fas_analysis_id' => FasAnalysis::factory(),
            'event_type' => FasEventType::OVER_1_5,
            'line' => 1.5,
            'estimated_probability' => $this->faker->randomFloat(2, 60, 95),
            'fas_score' => $this->faker->numberBetween(60, 100),
            'confidence' => ConfidenceLevel::HIGH,
            'eligible_top3' => $this->faker->boolean(30),
            'eligible_top5' => $this->faker->boolean(50),
        ];
    }
}
