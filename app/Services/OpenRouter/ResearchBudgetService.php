<?php

namespace App\Services\OpenRouter;

class ResearchBudgetService
{
    public function canSpend(float $estimatedCost): bool
    {
        return $estimatedCost <= config('openrouter.daily_budget_usd');
    }
}
