<?php

namespace Database\Factories;

use App\Models\FasRun;
use App\Models\Fixture;
use Illuminate\Database\Eloquent\Factories\Factory;

class FasAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fas_run_id' => FasRun::factory(),
            'fixture_id' => Fixture::factory(),
            'fii_score' => $this->faker->numberBetween(50, 100),
            'data_quality_score' => $this->faker->numberBetween(50, 100),
            'home_win_probability' => $this->faker->randomFloat(2, 10, 80),
            'draw_probability' => $this->faker->randomFloat(2, 10, 40),
            'away_win_probability' => $this->faker->randomFloat(2, 10, 80),
            'over_1_5_probability' => $this->faker->randomFloat(2, 50, 95),
            'over_2_5_probability' => $this->faker->randomFloat(2, 30, 80),
            'btts_probability' => $this->faker->randomFloat(2, 30, 80),
            'first_half_goal_probability' => $this->faker->randomFloat(2, 40, 85),
            'analysis_snapshot' => ['version' => '1.0.0'],
        ];
    }
}
