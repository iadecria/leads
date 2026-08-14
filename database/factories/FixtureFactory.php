<?php

namespace Database\Factories;

use App\Enums\FixtureStatus;
use App\Models\Competition;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class FixtureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => $this->faker->unique()->randomNumber(7),
            'competition_id' => Competition::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'season' => date('Y'),
            'round' => 'Regular Season - '.$this->faker->numberBetween(1, 38),
            'fixture_date' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
            'venue' => $this->faker->city().' Stadium',
            'status' => FixtureStatus::SCHEDULED,
        ];
    }
}
