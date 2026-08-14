<?php

namespace App\Interfaces\Engines;

use App\DTOs\Dataset\MatchDataset;
use App\DTOs\Engine\EventPrediction;

interface EventEngineInterface
{
    /**
     * Obtains an array of EventPrediction objects.
     * Most engines will return an array with a single EventPrediction.
     * The Result engine will return multiple (Home, Draw, Away).
     *
     * @return EventPrediction[]
     */
    public function calculate(MatchDataset $dataset): array;
}
