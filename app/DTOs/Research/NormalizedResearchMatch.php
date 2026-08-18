<?php

namespace App\DTOs\Research;

use Carbon\CarbonInterface;
use JsonSerializable;

class NormalizedResearchMatch implements JsonSerializable
{
    public function __construct(
        public string $date,
        public ?string $competition,
        public string $home_team,
        public string $away_team,
        public ?int $home_score_ft,
        public ?int $away_score_ft,
        public ?int $home_score_ht = null,
        public ?int $away_score_ht = null,
        public ?int $corners_home = null,
        public ?int $corners_away = null,
        public ?int $cards_home = null,
        public ?int $cards_away = null,
        public ?int $shots_home = null,
        public ?int $shots_away = null,
        public ?int $shots_on_target_home = null,
        public ?int $shots_on_target_away = null,
        public ?string $possession_home = null,
        public ?string $possession_away = null,
        public array $source_urls = [],
        public array $source_ids = [],
        public string $confidence = 'SINGLE_SOURCE_VALIDATED',
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function dateAsCarbon(): CarbonInterface
    {
        return now()->parse($this->date);
    }
}
