<?php

namespace Tests\Feature;

use App\Services\Discovery\GameDayDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameDayDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeBlocks(array $europa = [], array $brasil = [], array $americas = []): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push($this->blockResponse($europa), 200)
                ->push($this->blockResponse($brasil), 200)
                ->push($this->blockResponse($americas), 200),
        ]);
    }

    private function fakeAllBlocks(array $fixtures): void
    {
        $this->fakeBlocks($fixtures, $fixtures, $fixtures);
    }

    private function blockResponse(array $fixtures): array
    {
        return [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'fixtures' => $fixtures,
                            'sources' => ['https://example.com/source'],
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 800,
                'completion_tokens' => 1200,
                'total_tokens' => 2000,
            ],
        ];
    }

    private function fixture(string $competition, string $home, string $away, string $kickoff = '19:00', ?string $utc = null): array
    {
        return [
            'competition' => $competition,
            'home_team' => $home,
            'away_team' => $away,
            'kickoff' => $kickoff,
            'kickoff_utc' => $utc,
        ];
    }

    public function test_merges_blocks_and_deduplicates(): void
    {
        $this->fakeBlocks(
            [$this->fixture('Premier League', 'Arsenal', 'Chelsea', '14:30', '17:30')],
            [$this->fixture('Brasileirão Série A', 'Atlético-MG', 'Bragantino', '19:00', null)],
            [$this->fixture('Premier League', 'Arsenal', 'Chelsea', '14:30', '17:30')],
        );

        $result = app(GameDayDiscoveryService::class)->discover(now()->addDays(2)->format('Y-m-d'));

        $this->assertSame(3, $result['merged']);
        $this->assertSame(2, $result['deduplicated']);
        $this->assertSame(1, $result['dedup_removed']);
        $this->assertSame(3, $result['calls']);
        $this->assertSame(6000, $result['tokens']);
        $this->assertSame(2, $result['fixtures_eligible']);
    }

    public function test_utc_kickoff_converts_to_brt_window(): void
    {
        // 19:00 UTC = 16:00 BRT → Janela 1
        $this->fakeBlocks(
            [$this->fixture('UEFA Conference League Qualifying', 'NEC Nijmegen', 'Bodø/Glimt', '16:00', '19:00')],
            [],
            [],
        );

        $result = app(GameDayDiscoveryService::class)->discover(now()->addDays(1)->format('Y-m-d'));

        $this->assertSame('16:00', $result['window_1'][0]['kickoff_time']);
        $this->assertSame(1, $result['window_1'][0]['window']);
        $this->assertCount(0, $result['window_2']);
    }

    public function test_regression_finds_early_european_games(): void
    {
        $this->fakeBlocks(
            [
                $this->fixture('UEFA Champions League Qualifying', 'Celtic', 'LASK', '16:00', '19:00'),
                $this->fixture('UEFA Conference League Qualifying', 'NEC', 'Bodø/Glimt', '15:00', '18:00'),
                $this->fixture('UEFA Champions League Qualifying', 'Slovan Bratislava', 'Celje', '16:00', '19:00'),
            ],
            [],
            [],
        );

        $result = app(GameDayDiscoveryService::class)->discover(now()->addDays(2)->format('Y-m-d'));

        $teams = collect($result['window_1'])->pluck('home_team')->all();
        $this->assertContains('Celtic', $teams);
        $this->assertContains('NEC', $teams);
        $this->assertContains('Slovan Bratislava', $teams);
    }

    public function test_today_removes_started_fixtures(): void
    {
        $nowTz = now()->setTimezone('America/Sao_Paulo');

        $this->fakeAllBlocks([
            $this->fixture('Premier League', 'Arsenal', 'Chelsea', $nowTz->subHour()->format('H:i'), null),
            $this->fixture('Bundesliga', 'Bayern', 'Dortmund', $nowTz->addHours(5)->format('H:i'), null),
        ]);

        $result = app(GameDayDiscoveryService::class)->discover(now()->format('Y-m-d'));

        $this->assertSame(1, $result['fixtures_eligible']);
        $this->assertSame('Bayern', $result['window_1'][0]['home_team'] ?? $result['window_2'][0]['home_team']);
    }

    public function test_empty_returns_empty_windows(): void
    {
        $this->fakeAllBlocks([]);

        $result = app(GameDayDiscoveryService::class)->discover(now()->addDays(3)->format('Y-m-d'));

        $this->assertSame(0, $result['fixtures_found']);
        $this->assertSame(0, $result['fixtures_eligible']);
        $this->assertSame([], $result['window_1']);
        $this->assertSame([], $result['window_2']);
        $this->assertSame(3, $result['calls']);
    }

    public function test_excludes_disallowed_competitions(): void
    {
        $this->fakeAllBlocks([
            $this->fixture('Libertadores', 'Flamengo', 'River Plate', '21:30', null),
            $this->fixture('Série B', 'Time B', 'Time C', '19:00', null),
            $this->fixture('Premier League', 'Arsenal', 'Chelsea', '14:30', '17:30'),
        ]);

        $result = app(GameDayDiscoveryService::class)->discover(now()->addDays(2)->format('Y-m-d'));

        $this->assertSame(3, $result['deduplicated']);
        $this->assertSame(1, $result['fixtures_eligible']);
        $this->assertSame(2, $result['fixtures_excluded']);
    }

    public function test_debug_counts_per_block(): void
    {
        $this->fakeBlocks(
            [$this->fixture('Premier League', 'Arsenal', 'Chelsea', '14:30', '17:30')],
            [$this->fixture('Brasileirão Série A', 'Atlético-MG', 'Bragantino', '19:00', null)],
            [$this->fixture('MLS', 'Inter Miami', 'LA Galaxy', '20:00', null)],
        );

        $result = app(GameDayDiscoveryService::class)->discover(now()->addDays(1)->format('Y-m-d'));

        $this->assertSame(1, $result['discovery_europa']);
        $this->assertSame(1, $result['discovery_brasil']);
        $this->assertSame(1, $result['discovery_americas']);
    }
}