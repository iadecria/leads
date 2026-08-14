<?php

namespace App\Services\Audit\Validators;

use App\Models\FasEvent;
use App\Models\Fixture;

interface EventValidatorInterface
{
    /**
     * Valida um FasEvent contra o resultado/estatísticas reais de uma Fixture.
     * Retorna um array contendo:
     * - 'status' => FasAuditStatus::HIT|MISS|VOID|UNAVAILABLE|PENDING
     * - 'observed_value' => mixed
     * - 'rule' => string
     * - 'fixture_result' => array (opcional, extra context)
     */
    public function validate(FasEvent $event, Fixture $fixture): array;
}
