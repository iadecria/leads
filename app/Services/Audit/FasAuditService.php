<?php

namespace App\Services\Audit;

use App\Enums\FasAuditStatus;
use App\Enums\FasEventType;
use App\Models\FasAudit;
use App\Models\FasEvent;
use App\Models\FasRankingRun;
use App\Services\Audit\Validators\BttsValidator;
use App\Services\Audit\Validators\CardsValidator;
use App\Services\Audit\Validators\CornersValidator;
use App\Services\Audit\Validators\EventValidatorInterface;
use App\Services\Audit\Validators\FirstHalfGoalValidator;
use App\Services\Audit\Validators\OverGoalsValidator;
use App\Services\Audit\Validators\ResultValidator;
use Illuminate\Support\Facades\Log;

class FasAuditService
{
    /**
     * Executes the audit for a given FasRankingRun.
     * Optionally forces revalidation of previously audited events.
     */
    public function auditRun(FasRankingRun $run, bool $force = false): void
    {
        $rankings = $run->rankings()->with(['event.analysis.fixture.statistics'])->get();

        foreach ($rankings as $ranking) {
            $event = $ranking->event;
            $fixture = $event->analysis->fixture;

            // Check for existing audit
            $existingAudit = FasAudit::where('fas_ranking_run_id', $run->id)
                ->where('fas_event_id', $event->id)
                ->first();

            // If it exists, and it's already finished, and we aren't forcing, skip.
            if ($existingAudit && ! $force && in_array($existingAudit->status->value, ['HIT', 'MISS', 'VOID'])) {
                continue;
            }

            // Check if fixture is canceled/postponed
            if (in_array($fixture->status, ['CANC', 'PST', 'ABD'])) {
                $this->saveAudit($run, $ranking, FasAuditStatus::VOID, null, 'Fixture cancelled or abandoned', [], $existingAudit);

                continue;
            }

            // If it's not finished, mark as pending unless we somehow can validate it early (we strictly only validate finished games)
            if (! in_array($fixture->status, ['FT', 'AET', 'PEN'])) {
                $this->saveAudit($run, $ranking, FasAuditStatus::PENDING, null, 'Fixture not finished', [], $existingAudit);

                continue;
            }

            // Fixture is finished, let's validate
            $validator = $this->getValidatorForEvent($event);
            if (! $validator) {
                Log::warning("No validator found for event type: {$event->event_type->value}");

                continue;
            }

            try {
                $result = $validator->validate($event, $fixture);

                $this->saveAudit(
                    $run,
                    $ranking,
                    $result['status'],
                    $result['observed_value'],
                    $result['rule'],
                    $result['fixture_result'] ?? [],
                    $existingAudit
                );
            } catch (\Exception $e) {
                Log::error("Failed to validate event {$event->id} for run {$run->id}: ".$e->getMessage());
            }
        }
    }

    private function getValidatorForEvent(FasEvent $event): ?EventValidatorInterface
    {
        return match ($event->event_type) {
            FasEventType::OVER_1_5, FasEventType::OVER_2_5 => new OverGoalsValidator,
            FasEventType::FIRST_HALF_GOAL => new FirstHalfGoalValidator,
            FasEventType::BTTS => new BttsValidator,
            FasEventType::HOME_WIN, FasEventType::AWAY_WIN, FasEventType::DRAW => new ResultValidator,
            FasEventType::OVER_CORNERS => new CornersValidator,
            FasEventType::OVER_CARDS => new CardsValidator,
            default => null,
        };
    }

    private function saveAudit(
        FasRankingRun $run,
        $ranking,
        FasAuditStatus $status,
        $observedValue,
        string $rule,
        array $fixtureResult,
        ?FasAudit $existingAudit = null
    ): void {
        $event = $ranking->event;
        $auditVersion = config('fas.audit.version', '1.0.0');

        $payload = [
            'event' => $event->event_type->value,
            'line' => $event->line,
            'predicted_probability' => $event->estimated_probability,
            'fixture_result' => $fixtureResult,
            'observed_value' => $observedValue,
            'rule' => $rule,
            'status' => $status->value,
        ];

        if ($existingAudit) {
            $existingAudit->update([
                'status' => $status,
                'result_value' => $observedValue !== null ? (string) $observedValue : null,
                'is_correct' => $status === FasAuditStatus::HIT ? true : ($status === FasAuditStatus::MISS ? false : null),
                'payload' => $payload,
                'validated_at' => now(), // Updates revalidated time essentially
            ]);
        } else {
            FasAudit::create([
                'fas_ranking_run_id' => $run->id,
                'fas_ranking_id' => $ranking->id,
                'fas_event_id' => $event->id,
                'fixture_id' => $event->analysis->fixture_id,
                'status' => $status,
                'result_value' => $observedValue !== null ? (string) $observedValue : null,
                'is_correct' => $status === FasAuditStatus::HIT ? true : ($status === FasAuditStatus::MISS ? false : null),
                'audit_version' => $auditVersion,
                'ranking_version' => $run->engine_version,
                'engine_version' => config('fas.engine_version'),
                'dataset_version' => config('fas.dataset_version'),
                'payload' => $payload,
                'validated_at' => now(),
            ]);
        }
    }
}
