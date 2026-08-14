<?php

namespace App\Services\Fas\Engines;

use App\Models\FasEvent;
use App\Models\FasRanking;
use App\Models\FasRankingRun;
use App\Services\Fas\Calculators\CandidateScoreCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class FasRankingEngine
{
    public function __construct(
        protected CandidateScoreCalculator $candidateScoreCalculator
    ) {}

    public function generate(string $date, bool $force = false)
    {
        $analysisDate = Carbon::parse($date)->startOfDay();

        // Check if ranking already exists
        if (! $force) {
            $existingRun = FasRankingRun::whereDate('analysis_date', $analysisDate)->first();
            if ($existingRun) {
                return $existingRun;
            }
        }

        return DB::transaction(function () use ($analysisDate) {
            $run = FasRankingRun::create([
                'analysis_date' => $analysisDate,
                'ranking_version' => Config::get('fas.ranking.version', '1.0.0'),
                'engine_version' => Config::get('fas.engine_version', '1.0.0'),
                'dataset_version' => Config::get('fas.dataset_version', '1.0.0'),
                'config_snapshot' => Config::get('fas.ranking'),
                'cutoff_at' => now(), // Cutoff time is now
            ]);

            $events = FasEvent::with(['analysis.fixture.competition'])
                ->whereHas('analysis', function ($query) use ($analysisDate) {
                    $query->whereHas('run', function ($q) use ($analysisDate) {
                        $q->whereDate('analysis_date', $analysisDate);
                    })->orWhereHas('fixture', function ($q) use ($analysisDate) {
                        $q->whereDate('fixture_date', $analysisDate);
                    });
                })->get();

            $candidates = [];
            $rejected = [];

            foreach ($events as $event) {
                $evaluation = $this->evaluateEvent($event);

                if ($evaluation['status'] === 'REJECTED') {
                    $rejected[] = $evaluation;
                } else {
                    $candidates[] = $evaluation;
                }
            }

            // Deduplication and Ranking
            $rankedLists = $this->processRankingLists($candidates);

            // Persist
            $this->persistList($run, $rankedLists['top3'], 'TOP3');
            $this->persistList($run, $rankedLists['top5'], 'TOP5');
            $this->persistList($run, $rankedLists['watchlist'], 'WATCHLIST');
            $this->persistList($run, $rejected, 'REJECTED');

            return $run;
        });
    }

    protected function evaluateEvent(FasEvent $event): array
    {
        $fixture = $event->analysis->fixture;
        $payload = json_decode($event->payload, true);

        // 1. Pre-Kickoff Check
        if ($fixture->fixture_date <= now()) {
            return $this->reject($event, 'ALREADY_STARTED');
        }

        // 2. Fixture Status Check
        if (! in_array($fixture->status, ['NS', 'TBD'])) {
            return $this->reject($event, 'FIXTURE_NOT_SCHEDULED');
        }

        // 3. Eligibility Check (from fixture building phase)
        if ($fixture->fas_status !== 'ELIGIBLE') {
            return $this->reject($event, 'FIXTURE_INELIGIBLE');
        }

        // 4. Competition filter logic
        if ($fixture->competition && in_array(strtolower($fixture->competition->type), ['cup', 'friendly'])) {
            // For V1 we might exclude cups or friendlies, the prompt said friendlies and some others.
            // We'll rely on fas_status mostly, but specifically hardcode friendly rejection.
            if (str_contains(strtolower($fixture->competition->name), 'friendly') || str_contains(strtolower($fixture->competition->name), 'amistoso')) {
                return $this->reject($event, 'COMPETITION_FRIENDLY');
            }
        }

        // 5. INSUFFICIENT DATA
        if (($payload['sample_strength'] ?? 'INSUFFICIENT') === 'INSUFFICIENT') {
            return $this->reject($event, 'INSUFFICIENT_DATA');
        }

        // 6. Calculate Candidate Score
        $scoreResult = $this->candidateScoreCalculator->calculate($event);

        return [
            'status' => 'CANDIDATE',
            'event' => $event,
            'candidate_score' => $scoreResult['score'],
            'penalties' => $scoreResult['penalties'],
            'payload' => $payload,
        ];
    }

    protected function reject(FasEvent $event, string $reason): array
    {
        return [
            'status' => 'REJECTED',
            'event' => $event,
            'candidate_score' => 0,
            'penalties' => [],
            'watchlist_reason' => $reason,
        ];
    }

    protected function processRankingLists(array $candidates): array
    {
        $top3 = [];
        $top5 = [];
        $watchlist = [];

        // 1. Sort Candidates by Candidate Score DESC, Tie-breakers
        usort($candidates, function ($a, $b) {
            // 1. Candidate Score
            if ($a['candidate_score'] != $b['candidate_score']) {
                return $b['candidate_score'] <=> $a['candidate_score'];
            }
            // 2. Data Quality
            $dqA = $a['payload']['data_quality_score'] ?? 0;
            $dqB = $b['payload']['data_quality_score'] ?? 0;
            if ($dqA != $dqB) {
                return $dqB <=> $dqA;
            }
            // 3. Adjusted Probability
            $probA = $a['payload']['adjusted_probability'] ?? 0;
            $probB = $b['payload']['adjusted_probability'] ?? 0;
            if ($probA != $probB) {
                return $probB <=> $probA;
            }
            // 4. Effective Sample Size
            $esA = $a['payload']['effective_sample_size'] ?? 0;
            $esB = $b['payload']['effective_sample_size'] ?? 0;
            if ($esA != $esB) {
                return $esB <=> $esA;
            }

            // 5. Fixture ID
            return $a['event']->analysis->fixture_id <=> $b['event']->analysis->fixture_id;
        });

        $usedFixtures = [];
        $maxPerFixture = Config::get('fas.ranking.maximum_events_per_fixture', 1);

        foreach ($candidates as $candidate) {
            $fixtureId = $candidate['event']->analysis->fixture_id;

            // Deduplication
            if (($usedFixtures[$fixtureId] ?? 0) >= $maxPerFixture) {
                $candidate['watchlist_reason'] = 'SECOND_EVENT_SAME_FIXTURE';
                $watchlist[] = $candidate;

                continue;
            }

            // Determine Group
            $assignedGroup = $this->assignGroup($candidate);

            if ($assignedGroup === 'TOP3' && count($top3) < 3) {
                $top3[] = $candidate;
                $usedFixtures[$fixtureId] = ($usedFixtures[$fixtureId] ?? 0) + 1;
            } elseif (in_array($assignedGroup, ['TOP3', 'TOP5']) && count($top5) < 5) {
                // Notice that TOP5 includes TOP3 visually, but in DB we might separate or store 1-5.
                // We'll store top 3 in TOP3, and up to 2 in TOP5.
                if ($assignedGroup === 'TOP3' && count($top3) >= 3) {
                    $candidate['watchlist_reason'] = 'BELOW_TOP3_THRESHOLD_LIMIT'; // Demoted to TOP5
                }

                // If it's valid for TOP5 (which means it's TOP3 demoted or true TOP5)
                if (count($top3) + count($top5) < 5) {
                    // It goes into TOP5 tier
                    // But wait, the prompt says: "TOP5 contém necessariamente o TOP3. Depois selecionar até 2 candidatos."
                    // So we will just store TOP3 and TOP5 as distinct labels.
                    $top5[] = $candidate;
                    $usedFixtures[$fixtureId] = ($usedFixtures[$fixtureId] ?? 0) + 1;
                } else {
                    $candidate['watchlist_reason'] = 'BELOW_TOP5_LIMIT';
                    $watchlist[] = $candidate;
                }
            } else {
                $candidate['watchlist_reason'] = $candidate['watchlist_reason'] ?? 'DID_NOT_MEET_THRESHOLDS';
                $watchlist[] = $candidate;
            }
        }

        return [
            'top3' => $top3,
            'top5' => $top5,
            'watchlist' => $watchlist,
        ];
    }

    protected function assignGroup(array &$candidate): string
    {
        $event = $candidate['event'];
        $payload = $candidate['payload'];
        $score = $candidate['candidate_score'];

        $eventType = $event->event_type->value;
        $isOfficial = in_array($eventType, Config::get('fas.ranking.official_event_types', []));
        $isExperimental = in_array($eventType, Config::get('fas.ranking.experimental_event_types', []));

        if ($isExperimental) {
            $candidate['watchlist_reason'] = 'EXPERIMENTAL_ENGINE';

            return 'WATCHLIST';
        }

        if (str_contains($eventType, 'CARDS') && Config::get('fas.ranking.requirements.cards_requires_referee_for_top3', true)) {
            // For V1, no referee data
            $candidate['watchlist_reason'] = 'MISSING_REFEREE';

            return 'WATCHLIST';
        }

        if (! $isOfficial) {
            $candidate['watchlist_reason'] = 'UNOFFICIAL_EVENT';

            return 'WATCHLIST';
        }

        // Config Thresholds
        $top3Conf = Config::get('fas.ranking.top3', []);
        $top5Conf = Config::get('fas.ranking.top5', []);

        $prob = $payload['adjusted_probability'] ?? 0;
        $fasScore = $event->fas_score ?? 0;
        $dq = $payload['data_quality_score'] ?? 0;
        $confidence = $event->confidence->value;

        // TOP 3 Check
        if ($prob >= ($top3Conf['minimum_probability'] ?? 0.65) &&
            $fasScore >= ($top3Conf['minimum_fas_score'] ?? 70) &&
            $dq >= ($top3Conf['minimum_data_quality'] ?? 70) &&
            in_array($confidence, $top3Conf['allowed_confidence'] ?? [])) {
            return 'TOP3';
        }

        // TOP 5 Check
        if ($prob >= ($top5Conf['minimum_probability'] ?? 0.60) &&
            $fasScore >= ($top5Conf['minimum_fas_score'] ?? 60) &&
            $dq >= ($top5Conf['minimum_data_quality'] ?? 60) &&
            in_array($confidence, $top5Conf['allowed_confidence'] ?? [])) {
            return 'TOP5';
        }

        $candidate['watchlist_reason'] = 'BELOW_TOP5_THRESHOLD';

        return 'WATCHLIST';
    }

    protected function persistList(FasRankingRun $run, array $list, string $type)
    {
        $position = 1;
        foreach ($list as $item) {

            $correlationGroup = 'UNKNOWN';
            if (str_contains($item['event']->event_type->value, 'GOAL') || str_contains($item['event']->event_type->value, 'OVER') || str_contains($item['event']->event_type->value, 'BTTS')) {
                $correlationGroup = 'GOALS';
            } elseif (str_contains($item['event']->event_type->value, 'WIN') || $item['event']->event_type->value === 'DRAW') {
                $correlationGroup = 'RESULT';
            } elseif (str_contains($item['event']->event_type->value, 'CORNERS')) {
                $correlationGroup = 'CORNERS';
            } elseif (str_contains($item['event']->event_type->value, 'CARDS')) {
                $correlationGroup = 'CARDS';
            }

            FasRanking::create([
                'fas_ranking_run_id' => $run->id,
                'fas_event_id' => $item['event']->id,
                'ranking_type' => $type,
                'position' => $position++,
                'candidate_score' => $item['candidate_score'],
                'penalties' => $item['penalties'],
                'watchlist_reason' => $item['watchlist_reason'] ?? null,
                'correlation_group' => $correlationGroup,
            ]);
        }
    }
}
