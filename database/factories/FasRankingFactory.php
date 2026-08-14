<?php

namespace Database\Factories;

use App\Models\FasEvent;
use App\Models\FasRanking;
use App\Models\FasRankingRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class FasRankingFactory extends Factory
{
    protected $model = FasRanking::class;

    public function definition(): array
    {
        return [
            'fas_ranking_run_id' => FasRankingRun::factory(),
            'fas_event_id' => FasEvent::factory(),
            'ranking_type' => 'TOP3',
            'position' => $this->faker->numberBetween(1, 100),
            'candidate_score' => $this->faker->randomFloat(2, 50, 100),
            'watchlist_reason' => null,
        ];
    }
}
