<?php

namespace App\Http\Controllers;

use App\Jobs\RunFasAuditPipelineJob;
use App\Jobs\RunFasDailyPipelineJob;
use App\Models\FasExecutionRun;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FasExecutionController extends Controller
{
    public function index()
    {
        $executions = FasExecutionRun::latest()->paginate(20);

        return view('executions.index', compact('executions'));
    }

    public function show(FasExecutionRun $run)
    {
        return view('executions.show', compact('run'));
    }

    public function runDaily(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = Carbon::parse($request->date);

        if ($date->isPast() && ! $date->isToday()) {
            return response()->json([
                'error' => 'Análises pré-jogo só podem ser geradas para hoje ou datas futuras.',
            ], 422);
        }

        // Bloquear run concorrente
        $existing = FasExecutionRun::where('execution_type', 'DAILY_ANALYSIS')
            ->whereDate('analysis_date', $date->format('Y-m-d'))
            ->where('status', 'RUNNING')
            ->where('updated_at', '>=', now()->subMinute())
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Já existe uma execução em andamento para esta data.'], 422);
        }

        $run = FasExecutionRun::create([
            'execution_type' => 'DAILY_ANALYSIS',
            'analysis_date' => $date->format('Y-m-d'),
            'status' => 'PENDING',
        ]);

        RunFasDailyPipelineJob::dispatchSync($run);

        return response()->json(['message' => 'Execução iniciada', 'run_id' => $run->id]);
    }

    public function runAudit(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = Carbon::parse($request->date);

        if ($date->isFuture()) {
            return response()->json(['error' => 'Não há resultados a conferir para uma data futura.'], 422);
        }

        $rankingExists = \App\Models\FasRankingRun::whereDate('analysis_date', $date->format('Y-m-d'))->exists();
        if (! $rankingExists) {
            return response()->json(['error' => 'Nenhuma análise foi gerada para esta data.'], 422);
        }

        // Bloquear run concorrente
        $existing = FasExecutionRun::where('execution_type', 'RESULT_AUDIT')
            ->whereDate('analysis_date', $date->format('Y-m-d'))
            ->where('status', 'RUNNING')
            ->where('updated_at', '>=', now()->subMinute())
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Já existe uma auditoria em andamento para esta data.'], 422);
        }

        $run = FasExecutionRun::create([
            'execution_type' => 'RESULT_AUDIT',
            'analysis_date' => $date->format('Y-m-d'),
            'status' => 'PENDING',
        ]);

        RunFasAuditPipelineJob::dispatchSync($run);

        return response()->json(['message' => 'Auditoria iniciada', 'run_id' => $run->id]);
    }

    public function status(FasExecutionRun $run)
    {
        $progress = 0;

        if ($run->execution_type === 'DAILY_ANALYSIS') {
            if ($run->fixtures_status === 'COMPLETED') {
                $progress += 25;
            }
            if ($run->datasets_status === 'COMPLETED') {
                $progress += 25;
            }
            if ($run->analysis_status === 'COMPLETED') {
                $progress += 25;
            }
            if ($run->ranking_status === 'COMPLETED') {
                $progress += 25;
            }
        } else {
            if ($run->results_status === 'COMPLETED') {
                $progress += 50;
            }
            if ($run->audit_status === 'COMPLETED') {
                $progress += 50;
            }
        }

        return response()->json([
            'status' => $run->status,
            'current_step' => $run->current_step,
            'progress' => $progress,
            'summary' => $run->summary,
            'errors' => $run->errors,
        ]);
    }
}
