<?php

namespace App\DTOs;

class ResearchFixtureResult
{
    public function __construct(
        public array $fixture = [],
        public array $home_team = [],
        public array $away_team = [],
        public array $home_recent_matches = [],
        public array $away_recent_matches = [],
        public array $home_home_matches = [],
        public array $away_away_matches = [],
        public array $h2h = [],
        public array $standings = [],
        public array $injuries = [],
        public array $suspensions = [],
        public array $referee = [],
        public array $weather = [],
        public array $sources = [],
        public array $missing_fields = [],
        public array $conflicts = [],
        public string $research_quality = 'INSUFFICIENT',
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
