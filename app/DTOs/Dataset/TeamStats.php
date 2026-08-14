<?php

namespace App\DTOs\Dataset;

use JsonSerializable;

class TeamStats implements JsonSerializable
{
    public array $last5 = [];

    public array $last10 = [];

    public array $last20 = [];

    // For Home team, this means "Home last X"
    // For Away team, this means "Away last X"
    public array $splitLast5 = [];

    public array $splitLast10 = [];

    public static function fromArray(array $data): self
    {
        $stats = new self;
        $stats->last20 = $data['last20'] ?? [];
        $stats->last10 = $data['last10'] ?? [];
        $stats->last5 = $data['last5'] ?? [];
        $stats->splitLast10 = $data['splitLast10'] ?? [];
        $stats->splitLast5 = $data['splitLast5'] ?? [];

        // We need to recursively convert MetricValues if needed,
        // but since we read from DB payload, they might be arrays.
        // We'll hydrate them to object if they are accessed as objects.
        // For simplicity in V1 we cast them via json_decode json_encode to objects.
        $json = json_encode($data);
        $obj = json_decode($json);
        $stats->last20 = (array) ($obj->last20 ?? []);
        $stats->last10 = (array) ($obj->last10 ?? []);
        $stats->last5 = (array) ($obj->last5 ?? []);
        $stats->splitLast10 = (array) ($obj->splitLast10 ?? []);
        $stats->splitLast5 = (array) ($obj->splitLast5 ?? []);

        return $stats;
    }

    public function jsonSerialize(): array
    {
        return [
            'last_5' => $this->last5,
            'last_10' => $this->last10,
            'last_20' => $this->last20,
            'split_last_5' => $this->splitLast5,
            'split_last_10' => $this->splitLast10,
        ];
    }
}
