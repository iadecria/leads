<?php

namespace App\DTOs\Dataset;

use JsonSerializable;

class MetricValue implements JsonSerializable
{
    public function __construct(
        public readonly float|int|null $value,
        public readonly int $sampleSize = 0,
        public readonly ?int $count = null
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'count' => $this->count,
            'sample_size' => $this->sampleSize,
        ];
    }
}
