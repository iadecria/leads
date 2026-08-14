<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => $this->faker->unique()->randomNumber(6),
            'name' => $this->faker->city().' FC',
            'logo' => $this->faker->imageUrl(100, 100, 'sports'),
            'country' => $this->faker->country(),
        ];
    }
}
