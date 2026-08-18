# FAS Research

## Overview

This module adds a factual research layer on top of the existing FAS stack.

## Components

- `App\Services\OpenRouter\OpenRouterClient`
- `App\Services\OpenRouter\OpenRouterResearchProvider`
- `App\Contracts\FootballResearchProviderInterface`
- `App\DTOs\ResearchFixtureResult`
- `App\Services\OpenRouter\ResearchBudgetService`
- `App\Console\Commands\FasResearchCommand`

## Environment

Add these variables to `.env`:

```env
OPENROUTER_API_KEY=SUA_CHAVE_AQUI
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_MODEL=google/gemini-2.5-flash:floor
OPENROUTER_FALLBACK_MODELS=meta-llama/llama-3-8b-instruct:free
OPENROUTER_WEB_SEARCH_ENABLED=true
OPENROUTER_TIMEOUT=45
OPENROUTER_MAX_RETRIES=2
OPENROUTER_DAILY_BUDGET_USD=1.00
OPENROUTER_MAX_COST_PER_FIXTURE_USD=0.10
OPENROUTER_MAX_SEARCHES_PER_FIXTURE=3
OPENROUTER_TEMPERATURE=0.1
OPENROUTER_MAX_TOKENS=1200
OPENROUTER_SEARCH_ENGINE=parallel
OPENROUTER_SEARCH_CONTEXT_SIZE=turbo
```

## Request Shape

The client uses:

- `POST /chat/completions`
- `tools: [{ type: "openrouter:web_search", parameters: { engine: "parallel", search_context_size: "turbo" } }]`

## Notes

- The module is intentionally factual.
- It does not calculate probabilities.
- It does not replace the existing FAS Engine.
- It is safe to expand into cache, evidence, and hybrid mode later.
