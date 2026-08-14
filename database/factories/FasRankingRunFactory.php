<?php

namespace Database\Factories;

use App\Models\FasRankingRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class FasRankingRunFactory extends Factory
{
    protected $model = FasRankingRun::class;

    public function definition(): array
    {
        return [
            'analysis_date' => $this->faker->date(),
            'ranking_version' => '1.0.0',
            'engine_version' => '1.0.0',
            'dataset_version' => '1.0.0',
            'config_snapshot' => [],
            'cutoff_at' => now(),
            'generated_at' => now(),
        ];
    }
}
