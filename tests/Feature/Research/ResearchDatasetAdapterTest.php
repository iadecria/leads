<?php

namespace Tests\Feature\Research;

use App\DTOs\ResearchFixtureResult;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\Dataset\MatchDatasetBuilder;
use App\Services\Research\ResearchDatasetAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchDatasetAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_normalizes_and_deduplicates_research_matches()
    {
        $adapter = app(ResearchDatasetAdapter::class);

        $result = new ResearchFixtureResult(
            home_recent_matches: [
                ['date' => '2026-08-16T20:00:00Z', 'competition' => 'Liga', 'home_team' => 'SC Internacional', 'away_team' => 'Remo', 'home_score_ft' => 2, 'away_score_ft' => 0, 'source_urls' => ['https://example.com/1']],
                ['date' => '2026-08-16T20:00:00Z', 'competition' => 'Liga', 'home_team' => 'SC Internacional', 'away_team' => 'Remo', 'home_score_ft' => 2, 'away_score_ft' => 0, 'source_urls' => ['https://example.com/2']],
                ['date' => '2026-08-18T20:00:00Z', 'competition' => 'Liga', 'home_team' => 'SC Internacional', 'away_team' => 'Remo', 'home_score_ft' => 1, 'away_score_ft' => 1, 'source_urls' => ['https://example.com/future']],
            ],
            away_recent_matches: [
                ['date' => '2026-08-15T20:00:00Z', 'competition' => 'Liga', 'home_team' => 'ABC', 'away_team' => 'Remo', 'home_score_ft' => 0, 'away_score_ft' => 1, 'source_urls' => ['https://example.com/3']],
                ['date' => '2026-08-14T20:00:00Z', 'competition' => 'Liga', 'home_team' => 'XYZ', 'away_team' => 'Remo', 'home_score_ft' => 1, 'away_score_ft' => 2, 'source_urls' => ['https://example.com/4']],
            ],
            research_quality: 'HIGH'
        );

        $normalized = $adapter->normalize($result, 'Internacional', 'Remo', '2026-08-17T00:00:00Z');

        $this->assertCount(1, $normalized['home_recent_matches']);
        $this->assertCount(2, $normalized['away_recent_matches']);
        $this->assertSame('research_only', $normalized['trace']['mode']);
    }

    public function test_builder_can_build_dataset_from_research_result()
    {
        $home = Team::create(['external_id' => 1, 'name' => 'Internacional']);
        $away = Team::create(['external_id' => 2, 'name' => 'Remo']);
        $competition = Competition::create([
            'external_id' => 99,
            'name' => 'Copa Teste',
            'country' => 'Brasil',
            'fas_enabled' => true,
        ]);
        $fixture = Fixture::create([
            'external_id' => 10,
            'fixture_date' => '2026-08-17 20:00:00',
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'competition_id' => $competition->id,
            'season' => 2026,
            'status' => 'NS',
            'fas_status' => 'ELIGIBLE',
        ]);

        $matches = [];
        for ($i = 10; $i >= 1; $i--) {
            $matches[] = [
                'date' => "2026-08-{$i}T20:00:00Z",
                'competition' => 'Liga',
                'home_team' => 'Internacional',
                'away_team' => 'Opponent '.$i,
                'home_score_ft' => 2,
                'away_score_ft' => 1,
                'source_urls' => ['https://example.com/'.$i],
            ];
        }

        $research = new ResearchFixtureResult(
            home_recent_matches: $matches,
            away_recent_matches: $matches,
            research_quality: 'HIGH'
        );

        $builder = app(MatchDatasetBuilder::class);
        $record = $builder->buildFromResearch($fixture, $research, true);

        $payload = $record->payload;
        $this->assertSame(10, $payload['trace']['home_recent_matches']);
        $this->assertSame(10, $payload['trace']['away_recent_matches']);
        $this->assertSame(10, $payload['home_stats']['last_10']['goals']['over_15_rate']['sample_size']);
        $this->assertSame(10, $payload['away_stats']['last_10']['goals']['over_15_rate']['sample_size']);
    }
}
