<?php

namespace App\Http\Controllers;

use App\Models\FasExecutionRun;
use App\Models\ResearchFasRun;
use App\Services\OpenRouter\OpenRouterFasAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ResearchFasController extends Controller
{
    public function __construct(
        private OpenRouterFasAnalysisService $analysisService
    ) {}

    public function run(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'window' => 'nullable|integer|in:1,2',
        ]);

        $date = Carbon::parse($request->date);

        if ($date->isPast() && ! $date->isToday()) {
            return response()->json([
                'error' => 'Análises pré-jogo só podem ser feitas para hoje ou datas futuras.',
            ], 422);
        }

        // Pega a descoberta já persistida
        $discoveryRun = FasExecutionRun::where('execution_type', 'GAMEDAY_DISCOVERY')
            ->whereDate('analysis_date', $date->format('Y-m-d'))
            ->where('status', 'COMPLETED')
            ->latest()
            ->first();

        if (! $discoveryRun || empty($discoveryRun->summary['window_1'] ?? []) && empty($discoveryRun->summary['window_2'] ?? [])) {
            return response()->json([
                'error' => 'Nenhum jogo descoberto para esta data. Execute BUSCAR JOGOS DO DIA primeiro.',
            ], 422);
        }

        // Monta lista de fixtures: junta janela 1 + janela 2
        $fixtures = array_merge(
            $discoveryRun->summary['window_1'] ?? [],
            $discoveryRun->summary['window_2'] ?? []
        );

        try {
            $result = $this->analysisService->analyze(
                $date->format('Y-m-d'),
                $request->integer('window') ?: null,
                $fixtures
            );

            return response()->json([
                'message' => 'Análise FAS concluída.',
                'run_id' => $result['run_id'],
                'status' => $result['status'],
                'result' => $result['result'],
                'debug' => $result['debug'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha na análise FAS: '.$e->getMessage(),
            ], 500);
        }
    }

    public function latest(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $run = ResearchFasRun::whereDate('analysis_date', $request->date)
            ->latest()
            ->first();

        if (! $run) {
            return response()->json(['result' => null]);
        }

        return response()->json([
            'result' => $run->result,
            'debug' => $run->debug,
            'run_id' => $run->id,
            'status' => $run->status,
            'errors' => $run->errors,
        ]);
    }
}
