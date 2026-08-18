<?php

namespace App\Http\Controllers;

use App\Enums\ConfidenceLevel;
use App\Enums\RankingType;
use App\Models\FasRankingRun;
use App\Models\Fixture;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', today()->toDateString());
        $dateCarbon = Carbon::parse($date);

        $fixtures = Fixture::with(['competition.seasons', 'homeTeam', 'awayTeam'])
            ->whereDate('fixture_date', $date)
            ->orderBy('fixture_date')
            ->get();

        $rankingRun = FasRankingRun::with([
            'rankings.event.analysis.fixture.homeTeam',
            'rankings.event.analysis.fixture.awayTeam',
            'rankings.event.audits',
        ])
            ->whereDate('analysis_date', $date)
            ->latest('generated_at')
            ->first();

        $dashboardSections = $this->buildDashboardSections($rankingRun, $dateCarbon);
        $canGenerate = $dateCarbon->isToday() || $dateCarbon->isFuture();
        $canAudit = (bool) $rankingRun && ! $dateCarbon->isFuture();
        $statusText = $rankingRun ? ucfirst(strtolower($rankingRun->generated_at ? 'Concluído' : 'Aguardando')) : 'Aguardando';

        $auditStats = [];
        if ($rankingRun) {
            $auditStats = $this->buildAuditStats($rankingRun);
        }

        return view('dashboard', compact(
            'fixtures',
            'date',
            'rankingRun',
            'auditStats',
            'dashboardSections',
            'canGenerate',
            'canAudit',
            'statusText'
        ));
    }

    public function buildDatasets(Request $request)
    {
        $date = $request->input('date', today()->toDateString());
        // Since we are adding actions, let's call the console commands via Artisan for convenience.
        Artisan::call('fas:analyze', [
            'date' => $date,
            '--build-missing-dataset' => true,
        ]);

        return redirect()->route('dashboard', ['date' => $date])->with('success', 'FAS Executado para o dia!');
    }

    public function generateRanking(Request $request)
    {
        $date = $request->input('date', today()->toDateString());
        Artisan::call('fas:rank', [
            'date' => $date,
            '--force' => true,
        ]);

        return redirect()->route('dashboard', ['date' => $date])->with('success', 'Ranking gerado com sucesso!');
    }

    public function sync(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = $request->input('date');

        Artisan::call('fas:sync-fixtures', ['date' => $date]);

        return redirect()->route('dashboard', ['date' => $date])
            ->with('success', 'Jogos sincronizados com sucesso!');
    }

    public function audit(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = $request->input('date');

        Artisan::call('fas:audit', [
            'date' => $date,
            '--sync-results' => true,
        ]);

        return redirect()->route('dashboard', ['date' => $date])
            ->with('success', 'Confere Automático finalizado!');
    }

    public function showAudit(FasRankingRun $rankingRun)
    {
        $rankingRun->load([
            'rankings.event.analysis.fixture.homeTeam',
            'rankings.event.analysis.fixture.awayTeam',
            'rankings.event.audits',
        ]);

        return view('audits.show', compact('rankingRun'));
    }

    private function buildAuditStats(?FasRankingRun $rankingRun): array
    {
        $auditStats = [];

        foreach (['TOP3', 'TOP5'] as $type) {
            $rankings = $type === 'TOP3'
                ? $rankingRun->rankings->where('ranking_type', 'TOP3')
                : $rankingRun->rankings->whereIn('ranking_type', ['TOP3', 'TOP5']);

            $hits = 0;
            $misses = 0;
            $pending = 0;
            $void = 0;
            $unavailable = 0;

            foreach ($rankings as $ranking) {
                $audit = $ranking->event->audits->where('fas_ranking_run_id', $rankingRun->id)->first();
                if (! $audit) {
                    $pending++;

                    continue;
                }

                switch ($audit->status->value) {
                    case 'HIT': $hits++;
                        break;
                    case 'MISS': $misses++;
                        break;
                    case 'PENDING': $pending++;
                        break;
                    case 'VOID': $void++;
                        break;
                    case 'UNAVAILABLE': $unavailable++;
                        break;
                }
            }

            $audited = $hits + $misses;
            $rate = $audited > 0 ? round(($hits / $audited) * 100, 1) : 0;

            $auditStats[$type] = [
                'hits' => $hits,
                'misses' => $misses,
                'pending' => $pending,
                'void' => $void,
                'unavailable' => $unavailable,
                'audited' => $audited,
                'rate' => $rate,
                'total' => $rankings->count(),
            ];
        }

        return $auditStats;
    }

    private function buildDashboardSections(?FasRankingRun $rankingRun, Carbon $date): array
    {
        if (! $rankingRun) {
            return [
                'top3' => [],
                'top5' => [],
                'best_games' => [],
                'window_rankings' => [],
            ];
        }

        $rankings = $rankingRun->rankings->loadMissing('event.analysis.fixture.competition');

        $top3 = $rankings->where('ranking_type', 'TOP3')->values()->all();
        $top5 = $rankings->whereIn('ranking_type', ['TOP3', 'TOP5'])->values()->all();

        $bestGames = [];
        $windowRankings = [
            'MANHA' => [],
            'TARDE' => [],
            'NOITE' => [],
        ];

        foreach ($rankings as $ranking) {
            $fixture = $ranking->event?->analysis?->fixture;
            if (! $fixture) {
                continue;
            }

            $payload = is_array($ranking->event->payload) ? $ranking->event->payload : json_decode($ranking->event->payload, true);
            $probability = (float) ($payload['adjusted_probability'] ?? 0);
            $fasScore = (int) ($ranking->candidate_score ?? 0);
            $dataQuality = (int) ($payload['data_quality_score'] ?? 0);
            $confidence = $ranking->event->confidence?->value ?? (string) $ranking->event->confidence;

            $isStrong = $probability >= 0.68
                && $fasScore >= 70
                && $dataQuality >= 70
                && in_array($confidence, [ConfidenceLevel::MEDIUM->value, ConfidenceLevel::HIGH->value, ConfidenceLevel::VERY_HIGH->value], true);

            if (! $isStrong) {
                continue;
            }

            $kickoff = $fixture->fixture_date;
            $window = $this->getWindowLabel($kickoff);

            $windowRankings[$window][] = $this->serializeRankingItem($ranking);

            $fixtureKey = $fixture->id;
            if (! isset($bestGames[$fixtureKey])) {
                $bestGames[$fixtureKey] = [
                    'fixture_id' => $fixture->id,
                    'home_team' => $fixture->homeTeam?->name,
                    'away_team' => $fixture->awayTeam?->name,
                    'competition' => $fixture->competition?->name,
                    'kickoff' => $kickoff?->format('H:i'),
                    'items' => [],
                ];
            }

            $bestGames[$fixtureKey]['items'][] = $this->serializeRankingItem($ranking);
        }

        foreach ($windowRankings as $window => $items) {
            usort($items, fn ($a, $b) => $b['candidate_score'] <=> $a['candidate_score']);
            $windowRankings[$window] = $items;
        }

        uasort($bestGames, function ($a, $b) {
            $scoreA = collect($a['items'])->sum('candidate_score');
            $scoreB = collect($b['items'])->sum('candidate_score');

            return $scoreB <=> $scoreA;
        });

        return [
            'top3' => $this->serializeRankingCollection($top3),
            'top5' => $this->serializeRankingCollection($top5),
            'best_games' => array_values($bestGames),
            'window_rankings' => $windowRankings,
        ];
    }

    private function serializeRankingCollection(array $rankings): array
    {
        return array_map(fn ($ranking) => $this->serializeRankingItem($ranking), $rankings);
    }

    private function serializeRankingItem($ranking): array
    {
        $fixture = $ranking->event?->analysis?->fixture;
        $payload = is_array($ranking->event->payload) ? $ranking->event->payload : json_decode($ranking->event->payload, true);

        return [
            'ranking_id' => $ranking->id,
            'fixture_id' => $fixture?->id,
            'home_team' => $fixture?->homeTeam?->name,
            'away_team' => $fixture?->awayTeam?->name,
            'competition' => $fixture?->competition?->name,
            'event_type' => $ranking->event?->event_type?->value ?? (string) $ranking->event?->event_type,
            'candidate_score' => (float) $ranking->candidate_score,
            'adjusted_probability' => (float) ($payload['adjusted_probability'] ?? 0),
            'fas_score' => (int) ($ranking->event->fas_score ?? 0),
            'confidence' => $ranking->event->confidence?->value ?? (string) $ranking->event->confidence,
            'window' => $this->getWindowLabel($fixture?->fixture_date),
            'is_complement' => false,
        ];
    }

    private function getWindowLabel(?Carbon $kickoff): string
    {
        if (! $kickoff) {
            return 'NOITE';
        }

        $time = $kickoff->format('H:i');

        if ($time <= '12:59') {
            return 'MANHA';
        }

        if ($time <= '18:59') {
            return 'TARDE';
        }

        return 'NOITE';
    }
}
