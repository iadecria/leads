<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FasExecutionController;
use App\Http\Controllers\FixtureController;
use App\Http\Controllers\PerformanceController;
use App\Models\FasRankingRun;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('fas/executions')->group(function () {
    Route::get('/', [FasExecutionController::class, 'index'])->name('executions.index');
    Route::post('/daily', [FasExecutionController::class, 'runDaily'])->name('executions.daily');
    Route::post('/audit', [FasExecutionController::class, 'runAudit'])->name('executions.audit');
    Route::get('/{run}/status', [FasExecutionController::class, 'status'])->name('executions.status');
    Route::get('/{run}', [FasExecutionController::class, 'show'])->name('executions.show');
});

// Legacy / Advanced tools
Route::post('/dashboard/datasets', [DashboardController::class, 'buildDatasets'])->name('dashboard.datasets');
Route::get('/auditoria/{rankingRun}', [DashboardController::class, 'showAudit'])->name('audits.show');

Route::get('/ranking/{run}', function (FasRankingRun $run) {
    $run->load(['rankings.event.analysis.fixture.homeTeam', 'rankings.event.analysis.fixture.awayTeam']);

    return view('ranking.show', compact('run'));
})->name('ranking.show');

Route::get('/analises/{fixture}', [FixtureController::class, 'show'])->name('fixtures.show');
Route::post('/analises/{fixture}/dataset', [FixtureController::class, 'buildDataset'])->name('fixtures.dataset.build');
Route::post('/fixtures/{fixture}/run-fas', [FixtureController::class, 'runFasAnalysis'])->name('fixtures.run_fas');
Route::get('/analises/{fixture}/dataset/json', [FixtureController::class, 'showDataset'])->name('fixtures.dataset.show');

Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
Route::get('/performance/data', [PerformanceController::class, 'data'])->name('performance.data');
