<?php

namespace App\DTOs\Engine;

use JsonSerializable;

class EventPrediction implements JsonSerializable
{
    public function __construct(
        public string $event_type,
        public ?float $line,
        public ?float $raw_probability,
        public ?float $adjusted_probability,
        public float $prior_probability,
        public float $effective_sample_size,
        public string $sample_strength,
        public int $data_quality_score,
        public string $data_quality_level,
        public string $confidence,
        public int $fas_score,
        public array $positive_factors = [],
        public array $negative_factors = [],
        public array $components = [],
        public string $engine_version = '1.0.0'
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'event_type' => $this->event_type,
            'line' => $this->line,
            'raw_probability' => $this->raw_probability,
            'adjusted_probability' => $this->adjusted_probability,
            'prior_probability' => $this->prior_probability,
            'effective_sample_size' => $this->effective_sample_size,
            'sample_strength' => $this->sample_strength,
            'data_quality_score' => $this->data_quality_score,
            'data_quality_level' => $this->data_quality_level,
            'confidence' => $this->confidence,
            'fas_score' => $this->fas_score,
            'positive_factors' => $this->positive_factors,
            'negative_factors' => $this->negative_factors,
            'components' => $this->components,
            'engine_version' => $this->engine_version,
        ];
    }
}
