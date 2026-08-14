<?php

namespace App\Http\Controllers;

use App\Models\FasRankingRun;
use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', today()->toDateString());

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

        // Calculate rates if run exists
        $auditStats = [];
        if ($rankingRun) {
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
        }

        return view('dashboard', compact('fixtures', 'date', 'rankingRun', 'auditStats'));
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
}
