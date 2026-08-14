<?php

namespace App\DTOs\Dataset;

use JsonSerializable;

class MatchDataset implements JsonSerializable
{
    public array $fixture = [];

    public array $competition = [];

    public array $homeTeam = [];

    public array $awayTeam = [];

    public ?TeamStats $homeStats = null;

    public ?TeamStats $awayStats = null;

    public array $headToHead = [];

    public array $standings = [];

    public array $rest = [];

    public array $injuries = [];

    public array $coverage = [];

    public array $dataQuality = [];

    public array $trace = [];

    public string $datasetVersion;

    public function jsonSerialize(): array
    {
        return [
            'version' => $this->datasetVersion,
            'fixture' => $this->fixture,
            'competition' => $this->competition,
            'home_team' => $this->homeTeam,
            'away_team' => $this->awayTeam,
            'home_stats' => $this->homeStats,
            'away_stats' => $this->awayStats,
            'head_to_head' => $this->headToHead,
            'standings' => $this->standings,
            'rest' => $this->rest,
            'injuries' => $this->injuries,
            'coverage' => $this->coverage,
            'data_quality' => $this->dataQuality,
            'trace' => $this->trace,
        ];
    }

    public static function fromArray(array $data): self
    {
        $dataset = new self;
        $dataset->datasetVersion = $data['version'] ?? '1.0.0';
        $dataset->fixture = $data['fixture'] ?? [];
        $dataset->competition = $data['competition'] ?? [];
        $dataset->homeTeam = $data['home_team'] ?? [];
        $dataset->awayTeam = $data['away_team'] ?? [];

        $dataset->homeStats = TeamStats::fromArray($data['home_stats'] ?? []);
        $dataset->awayStats = TeamStats::fromArray($data['away_stats'] ?? []);

        $dataset->headToHead = $data['head_to_head'] ?? [];
        $dataset->standings = $data['standings'] ?? [];
        $dataset->rest = $data['rest'] ?? [];
        $dataset->injuries = $data['injuries'] ?? [];
        $dataset->coverage = $data['coverage'] ?? [];
        $dataset->dataQuality = $data['data_quality'] ?? [];
        $dataset->trace = $data['trace'] ?? [];

        return $dataset;
    }
}
