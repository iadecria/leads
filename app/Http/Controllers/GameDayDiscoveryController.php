<?php

namespace App\Http\Controllers;

use App\Models\FasExecutionRun;
use App\Services\Discovery\GameDayDiscoveryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GameDayDiscoveryController extends Controller
{
    public function __construct(
        private GameDayDiscoveryService $discoveryService
    ) {
    }

    public function search(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = Carbon::parse($request->date);

        if ($date->isPast() && ! $date->isToday()) {
            return response()->json([
                'error' => 'A busca de jogos só pode ser feita para hoje ou datas futuras.',
            ], 422);
        }

        $existing = FasExecutionRun::where('execution_type', 'GAMEDAY_DISCOVERY')
            ->whereDate('analysis_date', $date->format('Y-m-d'))
            ->where('status', 'RUNNING')
            ->where('updated_at', '>=', now()->subMinute())
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Já existe uma busca em andamento para esta data.'], 422);
        }

        $run = FasExecutionRun::create([
            'execution_type' => 'GAMEDAY_DISCOVERY',
            'analysis_date' => $date->format('Y-m-d'),
            'status' => 'RUNNING',
            'started_at' => now(),
            'current_step' => 'Buscando jogos do dia...',
        ]);

        try {
            $result = $this->discoveryService->discover($date->format('Y-m-d'));

            $run->update([
                'status' => 'COMPLETED',
                'finished_at' => now(),
                'current_step' => 'done',
                'summary' => $result,
            ]);

            return response()->json([
                'message' => 'Busca de jogos do dia concluída.',
                'run_id' => $run->id,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'FAILED',
                'finished_at' => now(),
                'errors' => [[
                    'step' => 'discovery',
                    'message' => $e->getMessage(),
                    'time' => now()->toDateTimeString(),
                ]],
            ]);

            return response()->json([
                'error' => 'Falha na busca de jogos: '.$e->getMessage(),
            ], 500);
        }
    }

    public function latest(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()));

        $run = FasExecutionRun::where('execution_type', 'GAMEDAY_DISCOVERY')
            ->whereDate('analysis_date', $date->format('Y-m-d'))
            ->latest()
            ->first();

        if (! $run) {
            return response()->json(['result' => null]);
        }

        return response()->json([
            'result' => $run->summary,
            'run_id' => $run->id,
            'status' => $run->status,
            'errors' => $run->errors,
        ]);
    }

    public function status(FasExecutionRun $run)
    {
        return response()->json([
            'status' => $run->status,
            'current_step' => $run->current_step,
            'summary' => $run->summary,
            'errors' => $run->errors,
        ]);
    }
}