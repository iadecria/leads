<?php

namespace App\Http\Controllers;

use App\Services\Performance\FasPerformanceService;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index(Request $request, FasPerformanceService $service)
    {
        $filters = $request->only([
            'date_from', 'date_to', 'engine_version', 'ranking_version',
        ]);

        $overall = $service->getOverallMetrics($filters);
        $tiers = $service->getTierMetrics($filters);
        $events = $service->getEventMetrics($filters);
        $calibration = $service->getProbabilityCalibration($filters);

        // Fetch competitions only if specifically asked or limit to top N to save memory
        // A full dashboard might lazily load this via ajax, but we'll load it here for simplicity
        $competitions = collect($service->getCompetitionMetrics($filters))->take(20)->toArray();

        return view('performance.index', compact(
            'overall', 'tiers', 'events', 'calibration', 'competitions', 'filters'
        ));
    }

    public function data(Request $request, FasPerformanceService $service)
    {
        $filters = $request->only([
            'date_from', 'date_to', 'engine_version', 'ranking_version',
        ]);

        return response()->json([
            'overall' => $service->getOverallMetrics($filters),
            'tiers' => $service->getTierMetrics($filters),
            'events' => $service->getEventMetrics($filters),
            'calibration' => $service->getProbabilityCalibration($filters),
        ]);
    }
}
