<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => $this->faker->unique()->randomNumber(5),
            'name' => $this->faker->company().' League',
            'country' => $this->faker->country(),
            'type' => 'League',
            'fas_tier' => $this->faker->numberBetween(1, 5),
            'fas_enabled' => true,
        ];
    }
}
