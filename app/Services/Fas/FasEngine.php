<?php

namespace App\Services\Fas;

use App\DTOs\Dataset\MatchDataset;
use App\DTOs\Engine\EventPrediction;
use App\Models\FasAnalysis;
use App\Models\FasEvent;
use App\Services\Fas\Engines\BttsEngine;
use App\Services\Fas\Engines\CardsEngine;
use App\Services\Fas\Engines\CornersEngine;
use App\Services\Fas\Engines\FirstHalfGoalEngine;
use App\Services\Fas\Engines\Over15Engine;
use App\Services\Fas\Engines\Over25Engine;
use App\Services\Fas\Engines\ResultEngine;
use Illuminate\Support\Facades\DB;

class FasEngine
{
    protected array $engines;

    public function __construct(
        Over15Engine $over15,
        Over25Engine $over25,
        FirstHalfGoalEngine $firstHalf,
        BttsEngine $btts,
        ResultEngine $result,
        CornersEngine $corners,
        CardsEngine $cards
    ) {
        $this->engines = [
            $over15, $over25, $firstHalf, $btts, $result, $corners, $cards,
        ];
    }

    public function analyze(MatchDataset $dataset, ?int $runId = null): FasAnalysis
    {
        return DB::transaction(function () use ($dataset, $runId) {
            $analysis = FasAnalysis::create([
                'fas_run_id' => $runId, // Can be null if it's a standalone generation
                'fixture_id' => $dataset->fixture['id'],
                'data_quality_score' => $dataset->dataQuality['score'],
                'analysis_snapshot' => $dataset->jsonSerialize(),
                // Keep the legacy columns null or update them later
            ]);

            $allPredictions = [];

            foreach ($this->engines as $engine) {
                $predictions = $engine->calculate($dataset);
                $allPredictions = array_merge($allPredictions, $predictions);
            }

            foreach ($allPredictions as $prediction) {
                if ($prediction->sample_strength === 'INSUFFICIENT' && $prediction->adjusted_probability === null) {
                    continue; // Do not persist completely insufficient predictions unless debugging
                }

                FasEvent::create([
                    'fas_analysis_id' => $analysis->id,
                    'event_type' => $prediction->event_type,
                    'line' => $prediction->line,
                    'estimated_probability' => $prediction->adjusted_probability,
                    'fas_score' => $prediction->fas_score,
                    'confidence' => $prediction->confidence,
                    'payload' => json_encode($prediction->jsonSerialize()),
                    // eligible_top3/top5 are false by default
                ]);

                // Also populate the legacy summary columns in analysis if we want for dashboard sorting
                $this->updateSummaryColumns($analysis, $prediction);
            }

            $analysis->save();

            return $analysis;
        });
    }

    private function updateSummaryColumns(FasAnalysis $analysis, EventPrediction $prediction)
    {
        switch ($prediction->event_type) {
            case 'HOME_WIN':
                $analysis->home_win_probability = $prediction->adjusted_probability;
                break;
            case 'DRAW':
                $analysis->draw_probability = $prediction->adjusted_probability;
                break;
            case 'AWAY_WIN':
                $analysis->away_win_probability = $prediction->adjusted_probability;
                break;
            case 'OVER_1_5':
                $analysis->over_1_5_probability = $prediction->adjusted_probability;
                break;
            case 'OVER_2_5':
                $analysis->over_2_5_probability = $prediction->adjusted_probability;
                break;
            case 'BTTS':
                $analysis->btts_probability = $prediction->adjusted_probability;
                break;
            case 'FIRST_HALF_GOAL':
                $analysis->first_half_goal_probability = $prediction->adjusted_probability;
                break;
        }
    }
}
