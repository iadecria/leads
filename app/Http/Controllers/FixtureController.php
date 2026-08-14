<?php

namespace App\Http\Controllers;

use App\Jobs\BuildMatchDatasetJob;
use App\Jobs\RunFasAnalysisJob;
use App\Models\Fixture;
use App\Models\MatchDatasetRecord;

class FixtureController extends Controller
{
    public function show(Fixture $fixture)
    {
        $fixture->load(['competition', 'homeTeam', 'awayTeam', 'analyses.events']);
        $dataset = MatchDatasetRecord::where('fixture_id', $fixture->id)->latest('generated_at')->first();

        return view('fixtures.show', compact('fixture', 'dataset'));
    }

    public function buildDataset(Fixture $fixture)
    {
        BuildMatchDatasetJob::dispatch($fixture, true, false);

        return redirect()->route('fixtures.show', $fixture->id)->with('success', 'Dataset generation job dispatched!');
    }

    public function showDataset(Fixture $fixture)
    {
        $dataset = MatchDatasetRecord::where('fixture_id', $fixture->id)->latest('generated_at')->firstOrFail();

        return response()->json($dataset->payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function runFasAnalysis(Fixture $fixture)
    {
        RunFasAnalysisJob::dispatchSync($fixture->id, false, null);

        return redirect()->route('fixtures.show', $fixture->id)->with('success', 'FAS Analysis executed successfully!');
    }
}
