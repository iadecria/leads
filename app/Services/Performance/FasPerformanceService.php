<?php

namespace App\Services\Performance;

use App\Models\FasAudit;
use App\Models\FasRankingRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FasPerformanceService
{
    /**
     * Retorna os Rankings Runs filtrados pelo request.
     */
    private function getFilteredRuns(array $filters): Collection
    {
        $query = FasRankingRun::query();

        if (! empty($filters['date_from'])) {
            $query->whereDate('analysis_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('analysis_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['engine_version'])) {
            $query->where('engine_version', $filters['engine_version']);
        }

        if (! empty($filters['ranking_version'])) {
            $query->where('ranking_version', $filters['ranking_version']);
        }

        return $query->get();
    }

    /**
     * Retorna uma query baseada em FasAudit com os devidos joins e filtros aplicados.
     */
    private function getBaseAuditQuery(array $filters)
    {
        $runIds = $this->getFilteredRuns($filters)->pluck('id');

        $query = DB::table('fas_audits')
            ->join('fas_rankings', 'fas_audits.fas_ranking_id', '=', 'fas_rankings.id')
            ->join('fas_events', 'fas_audits.fas_event_id', '=', 'fas_events.id')
            ->join('fas_analyses', 'fas_events.fas_analysis_id', '=', 'fas_analyses.id')
            ->join('fixtures', 'fas_analyses.fixture_id', '=', 'fixtures.id')
            ->whereIn('fas_audits.fas_ranking_run_id', $runIds);

        if (! empty($filters['ranking_type'])) {
            $query->where('fas_rankings.ranking_type', $filters['ranking_type']);
        }

        if (! empty($filters['competition_id'])) {
            $query->where('fixtures.competition_id', $filters['competition_id']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('fas_events.event_type', $filters['event_type']);
        }

        return $query;
    }

    /**
     * Calcula as métricas padrões a partir de uma query agrupada ou geral.
     */
    private function calculateMetrics(Collection $audits): array
    {
        $total = $audits->count();

        $hits = $audits->where('status', 'HIT')->count();
        $misses = $audits->where('status', 'MISS')->count();
        $voids = $audits->where('status', 'VOID')->count();
        $unavailable = $audits->where('status', 'UNAVAILABLE')->count();
        $pending = $audits->where('status', 'PENDING')->count();

        $audited = $hits + $misses;
        $hitRate = $audited > 0 ? round(($hits / $audited) * 100, 2) : 0;

        // Brier Score
        $brierSum = 0;
        $logLossSum = 0;
        $epsilon = 1e-15;

        // Probabilidades médias apenas dos validados
        $validAudits = $audits->filter(fn ($a) => in_array($a->status, ['HIT', 'MISS']));
        $avgProb = $validAudits->avg('estimated_probability');

        $avgFasScore = $validAudits->avg('fas_score');
        $avgCandidateScore = $validAudits->avg('candidate_score');

        foreach ($validAudits as $a) {
            $p = $a->estimated_probability;
            $y = $a->status === 'HIT' ? 1 : 0;

            // Brier
            $brierSum += pow($p - $y, 2);

            // Log Loss (clipping p)
            $pClipped = max($epsilon, min(1 - $epsilon, $p));
            $logLossSum += -($y * log($pClipped) + (1 - $y) * log(1 - $pClipped));
        }

        $brierScore = $audited > 0 ? round($brierSum / $audited, 4) : null;
        $logLoss = $audited > 0 ? round($logLossSum / $audited, 4) : null;

        $calibrationGap = ($audited > 0 && $avgProb !== null)
            ? round(($hitRate / 100) - $avgProb, 4)
            : null;

        return [
            'total_predictions' => $total,
            'audited_predictions' => $audited,
            'hits' => $hits,
            'misses' => $misses,
            'voids' => $voids,
            'unavailable' => $unavailable,
            'pending' => $pending,
            'hit_rate' => $hitRate,
            'average_probability' => $avgProb ? round($avgProb, 4) : null,
            'average_fas_score' => $avgFasScore ? round($avgFasScore, 2) : null,
            'average_candidate_score' => $avgCandidateScore ? round($avgCandidateScore, 2) : null,
            'calibration_gap' => $calibrationGap,
            'brier_score' => $brierScore,
            'log_loss' => $logLoss,
        ];
    }

    public function getOverallMetrics(array $filters = []): array
    {
        $query = $this->getBaseAuditQuery($filters);

        $audits = $query->select(
            'fas_audits.status',
            'fas_events.estimated_probability',
            'fas_events.fas_score',
            'fas_rankings.candidate_score'
        )->get();

        return $this->calculateMetrics($audits);
    }

    public function getTierMetrics(array $filters = []): array
    {
        $tiers = ['TOP3', 'TOP5', 'WATCHLIST'];
        $results = [];

        foreach ($tiers as $tier) {
            $tierFilters = $filters;
            $tierFilters['ranking_type'] = $tier;

            $query = $this->getBaseAuditQuery($tierFilters);

            $audits = $query->select(
                'fas_audits.status',
                'fas_events.estimated_probability',
                'fas_events.fas_score',
                'fas_rankings.candidate_score',
                'fas_rankings.ranking_type'
            );

            // Special handling for TOP5 (includes TOP3 and TOP5 positions 1-5 usually, but filtering by ranking_type is explicit)
            if ($tier === 'TOP5') {
                unset($tierFilters['ranking_type']);
                $query = $this->getBaseAuditQuery($tierFilters);
                $audits = $query->whereIn('fas_rankings.ranking_type', ['TOP3', 'TOP5'])
                    ->where('fas_rankings.position', '<=', 5)
                    ->select(
                        'fas_audits.status',
                        'fas_events.estimated_probability',
                        'fas_events.fas_score',
                        'fas_rankings.candidate_score'
                    );
            }

            $results[strtolower($tier)] = $this->calculateMetrics($audits->get());
        }

        return $results;
    }

    public function getEventMetrics(array $filters = []): array
    {
        $query = $this->getBaseAuditQuery($filters);

        $audits = $query->select(
            'fas_audits.status',
            'fas_events.estimated_probability',
            'fas_events.fas_score',
            'fas_rankings.candidate_score',
            'fas_events.event_type'
        )->get();

        $grouped = $audits->groupBy('event_type');

        $results = [];
        foreach ($grouped as $eventType => $items) {
            $results[$eventType] = $this->calculateMetrics($items);
        }

        // Sort by audited count desc
        uasort($results, fn ($a, $b) => $b['audited_predictions'] <=> $a['audited_predictions']);

        return $results;
    }

    public function getProbabilityCalibration(array $filters = []): array
    {
        $query = $this->getBaseAuditQuery($filters);
        $audits = $query->select(
            'fas_audits.status',
            'fas_events.estimated_probability'
        )->get();

        $buckets = config('fas.performance.probability_buckets', []);
        $results = [];

        foreach ($buckets as $label => $range) {
            $items = $audits->filter(function ($a) use ($range) {
                $p = $a->estimated_probability;

                return $p !== null && $p >= $range[0] && $p <= $range[1];
            });

            $metrics = $this->calculateMetrics($items);
            $results[$label] = array_merge($metrics, ['label' => $label]);
        }

        return $results;
    }

    public function getFeatureMetrics(string $feature, array $filters = []): array
    {
        $query = $this->getBaseAuditQuery($filters);

        $audits = $query->select(
            'fas_audits.status',
            'fas_events.estimated_probability',
            'fas_events.fas_score',
            'fas_events.confidence',
            'fas_events.data_quality_score',
            'fas_rankings.candidate_score',
            'fas_rankings.position'
        )->get();

        $grouped = [];

        switch ($feature) {
            case 'confidence':
                $grouped = $audits->groupBy('confidence');
                break;
            case 'position':
                $grouped = $audits->groupBy('position');
                break;
            case 'fas_score':
                $buckets = config('fas.performance.fas_score_buckets', []);
                $grouped = collect();
                foreach ($buckets as $label => $range) {
                    $grouped[$label] = $audits->filter(function ($a) use ($range) {
                        return $a->fas_score >= $range[0] && $a->fas_score <= $range[1];
                    });
                }
                break;
        }

        $results = [];
        foreach ($grouped as $key => $items) {
            $results[$key] = $this->calculateMetrics($items);
        }

        if ($feature === 'position') {
            ksort($results);
        }

        return $results;
    }

    public function getCompetitionMetrics(array $filters = []): array
    {
        $query = $this->getBaseAuditQuery($filters);

        $audits = $query->select(
            'fas_audits.status',
            'fas_events.estimated_probability',
            'fas_events.fas_score',
            'fas_rankings.candidate_score',
            'fixtures.competition_id',
            'fixtures.season'
        )->get();

        $grouped = $audits->groupBy(function ($item) {
            return $item->competition_id.'-'.$item->season;
        });

        $results = [];
        foreach ($grouped as $key => $items) {
            // Need competition info, fetch if necessary but for now just pass keys
            $results[$key] = array_merge(
                $this->calculateMetrics($items),
                [
                    'competition_id' => $items->first()->competition_id,
                    'season' => $items->first()->season,
                ]
            );
        }

        // Sort by audited count desc
        uasort($results, fn ($a, $b) => $b['audited_predictions'] <=> $a['audited_predictions']);

        return $results;
    }
}
