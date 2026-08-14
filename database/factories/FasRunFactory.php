<?php

namespace Database\Factories;

use App\Enums\FasRunStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class FasRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'analysis_date' => $this->faker->date(),
            'status' => FasRunStatus::COMPLETED,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
            'algorithm_version' => '1.0.0',
            'data_quality_score' => $this->faker->numberBetween(70, 100),
            'fixtures_found' => $this->faker->numberBetween(50, 200),
            'fixtures_eligible' => $this->faker->numberBetween(30, 100),
            'fixtures_analyzed' => $this->faker->numberBetween(20, 90),
        ];
    }
}
