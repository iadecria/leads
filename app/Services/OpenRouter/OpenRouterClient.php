<?php

namespace App\Services\OpenRouter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterClient
{
    public function chat(array $messages, array $options = []): array
    {
        return $this->chatWithDebug($messages, $options)['response'];
    }

    public function chatWithDebug(array $messages, array $options = []): array
    {
        $payload = array_merge([
            'model' => config('openrouter.model'),
            'messages' => $messages,
            'temperature' => config('openrouter.temperature'),
            'max_tokens' => config('openrouter.max_tokens'),
            'tools' => [
                [
                    'type' => 'openrouter:web_search',
                    'parameters' => [
                        'engine' => config('openrouter.engine', 'parallel'),
                        'search_context_size' => config('openrouter.search_context_size', 'turbo'),
                        'max_results' => 5,
                        'max_total_results' => 15,
                    ],
                ],
            ],
        ], $options);

        $models = $this->modelList($payload['model'] ?? null);
        $lastException = null;
        $attempts = [];

        foreach ($models as $model) {
            $payload['model'] = $model;

            try {
                $response = $this->request()->post('/chat/completions', $payload);
                if ($response->successful()) {
                    $decoded = $response->json();

                    return [
                        'response' => $decoded,
                        'debug' => [
                            'requested_model' => $model,
                            'attempted_models' => $attempts,
                            'web_search_requested' => $this->isWebSearchRequested($payload),
                            'web_search_executed' => $this->hasToolCalls($decoded) || $this->hasAnnotations($decoded),
                            'search_results_count' => $this->countSearchResults($decoded),
                            'raw_response' => $decoded,
                            'usage' => data_get($decoded, 'usage', []),
                        ],
                    ];
                }

                $lastException = new RuntimeException('OpenRouter request failed with status '.$response->status().': '.$response->body());
                $attempts[] = [
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempts[] = [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ];
            }
        }

        throw new RuntimeException('OpenRouter request failed after trying all models.', 0, $lastException);
    }

    public function researchJson(string $prompt, array $options = []): array
    {
        return $this->researchJsonWithDebug($prompt, $options)['result'];
    }

    public function researchJsonWithDebug(string $prompt, array $options = []): array
    {
        $chat = $this->chatWithDebug([
            ['role' => 'system', 'content' => 'You are a factual football research assistant. Return strict JSON only. Never invent data.'],
            ['role' => 'user', 'content' => $prompt],
        ], $options);

        if ($this->isSearchInvalid($chat['debug'])) {
            $fallbackChat = $this->chatWithDebug([
                ['role' => 'system', 'content' => 'You MUST use web search before answering. Do not answer from model memory. Return strict JSON only. Never invent data.'],
                ['role' => 'user', 'content' => $prompt],
            ], array_merge($options, [
                'model' => 'openrouter/auto',
                'plugins' => [
                    ['id' => 'web'],
                ],
                'fallback_reason' => 'SEARCH_NOT_EXECUTED',
                'search_strategy' => 'WEB_PLUGIN_FALLBACK',
            ]));

            $fallbackDebug = $fallbackChat['debug'];
            $fallbackDebug['fallback_model'] = $fallbackDebug['requested_model'] ?? null;
            $fallbackDebug['reason_for_fallback'] = 'SEARCH_NOT_EXECUTED';
            $fallbackDebug['search_strategy'] = 'WEB_PLUGIN_FALLBACK';
            $fallbackDebug['primary_model'] = $chat['debug']['requested_model'] ?? null;
            $fallbackDebug['primary_debug'] = $chat['debug'];

            $response = $fallbackChat['response'];
            $content = data_get($response, 'choices.0.message.content', '{}');
            $parsed = $this->decodeJsonPayload($content);

            return [
                'result' => $parsed,
                'debug' => array_merge($fallbackDebug, [
                    'content' => $content,
                    'parsed_response' => $parsed,
                    'tool_calls' => data_get($response, 'choices.0.message.tool_calls', []),
                    'annotations' => data_get($response, 'choices.0.message.annotations', []),
                    'web_search_executed' => $this->hasToolCalls($response) || $this->hasAnnotations($response) || $this->hasSearchEvidence($response),
                    'web_search_requested' => true,
                ]),
            ];
        }

        $response = $chat['response'];
        $content = data_get($response, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenRouter returned an empty research response.');
        }
        try {
            $parsed = $this->decodeJsonPayload($content);
        } catch (\Throwable $e) {
            $parsed = [];

            return [
                'result' => $parsed,
                'debug' => array_merge($chat['debug'], [
                    'content' => $content,
                    'parsed_response' => $parsed,
                    'parse_error' => $e->getMessage(),
                    'research_status' => 'INSUFFICIENT_RESEARCH',
                    'tool_calls' => data_get($response, 'choices.0.message.tool_calls', []),
                    'annotations' => data_get($response, 'choices.0.message.annotations', []),
                ]),
            ];
        }

        return [
            'result' => $parsed,
            'debug' => array_merge($chat['debug'], [
                'content' => $content,
                'parsed_response' => $parsed,
                'tool_calls' => data_get($response, 'choices.0.message.tool_calls', []),
                'annotations' => data_get($response, 'choices.0.message.annotations', []),
            ]),
        ];
    }

    public function researchWebPluginJsonWithDebug(string $prompt, array $options = []): array
    {
        $researchModel = config('openrouter.research_model', config('openrouter.model'));
        $chat = $this->chatWithDebug([
            ['role' => 'system', 'content' => 'You are a factual football research assistant. Return strict JSON only. Never invent data.'],
            ['role' => 'user', 'content' => $prompt],
        ], array_merge($options, [
            'model' => $researchModel,
            'tools' => [],
            'plugins' => [
                ['id' => 'web'],
            ],
            'search_strategy' => 'WEB_PLUGIN',
            'tool_choice' => 'auto',
        ]));

        $response = $chat['response'];
        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenRouter returned an empty research response.');
        }

        try {
            $parsed = $this->decodeJsonPayload($content);
        } catch (\Throwable $e) {
            return [
                'result' => [],
                'debug' => array_merge($chat['debug'], [
                    'content' => $content,
                    'parsed_response' => [],
                    'parse_error' => $e->getMessage(),
                    'research_status' => 'INSUFFICIENT_RESEARCH',
                    'tool_calls' => data_get($response, 'choices.0.message.tool_calls', []),
                    'annotations' => data_get($response, 'choices.0.message.annotations', []),
                ]),
            ];
        }
        $sources = $this->extractSourcesFromResearchResponse($response, $parsed);

        return [
            'result' => $parsed,
            'debug' => array_merge($chat['debug'], [
                'requested_model' => $researchModel,
                'resolved_model' => data_get($response, 'model', $researchModel),
                'resolved_provider' => data_get($response, 'provider'),
                'search_strategy' => 'WEB_PLUGIN',
                'web_search_requested' => true,
                'web_search_executed' => true,
                'sources_count' => count($sources),
                'sources' => $sources,
                'content' => $content,
                'parsed_response' => $parsed,
                'tool_calls' => data_get($response, 'choices.0.message.tool_calls', []),
                'annotations' => data_get($response, 'choices.0.message.annotations', []),
            ]),
        ];
    }

    private function request(): PendingRequest
    {
        $apiKey = config('openrouter.api_key');
        if (empty($apiKey) || $apiKey === 'SUA_CHAVE_AQUI') {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim(config('openrouter.base_url'), '/'))
            ->withOptions([
                'proxy' => '',
            ])
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'HTTP-Referer' => config('openrouter.referer'),
                'X-Title' => config('openrouter.app_name'),
                'Content-Type' => 'application/json',
            ])
            ->timeout(config('openrouter.timeout'))
            ->retry(config('openrouter.max_retries'), 1000);
    }

    private function modelList(?string $primary): array
    {
        $models = array_values(array_filter(array_merge(
            [$primary ?: config('openrouter.model')],
            config('openrouter.fallback_models', [])
        )));

        return array_unique($models);
    }

    private function decodeJsonPayload(string $content): array
    {
        $candidates = [
            $content,
            trim(preg_replace('/^```(?:json)?\s*/i', '', trim($content))),
            trim(preg_replace('/\s*```$/', '', trim($content))),
        ];

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $content, $match)) {
            $candidates[] = $match[1];
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $candidates[] = $match[0];
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            foreach ($this->normalizeJsonCandidates($candidate) as $normalized) {
                $decoded = json_decode($normalized, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new RuntimeException('OpenRouter returned invalid JSON: '.json_last_error_msg());
    }

    private function isWebSearchRequested(array $payload): bool
    {
        return ! empty($payload['tools']);
    }

    private function hasToolCalls(array $decoded): bool
    {
        return ! empty(data_get($decoded, 'choices.0.message.tool_calls', []));
    }

    private function hasAnnotations(array $decoded): bool
    {
        return ! empty(data_get($decoded, 'choices.0.message.annotations', []));
    }

    private function hasSearchEvidence(array $decoded): bool
    {
        return $this->countSearchResults($decoded) > 0;
    }

    private function isSearchInvalid(array $debug): bool
    {
        $response = $debug['raw_response'] ?? [];
        $hasEvidence = $this->hasToolCalls($response) || $this->hasAnnotations($response) || $this->hasSearchEvidence($response);
        $hasSources = ! empty(data_get($debug, 'parsed_response.sources', []));

        return ($debug['web_search_requested'] ?? false)
            && ! $hasEvidence
            && ! $hasSources;
    }

    private function countSearchResults(array $decoded): int
    {
        $toolCalls = data_get($decoded, 'choices.0.message.tool_calls', []);

        return is_array($toolCalls) ? count($toolCalls) : 0;
    }

    private function extractSourcesFromResearchResponse(array $response, array $parsed): array
    {
        $sources = [];

        foreach (data_get($response, 'choices.0.message.annotations', []) as $annotation) {
            if (is_array($annotation)) {
                $url = $annotation['url'] ?? $annotation['source_url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $sources[] = $url;
                }
            }
        }

        foreach ((array) ($parsed['sources'] ?? []) as $source) {
            if (is_string($source) && $source !== '') {
                $sources[] = $source;
            } elseif (is_array($source)) {
                $url = $source['url'] ?? $source['source_url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $sources[] = $url;
                }
            }
        }

        return array_values(array_unique($sources));
    }

    private function normalizeJsonCandidates(string $content): array
    {
        $trimmed = trim($content);
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', $trimmed);
        $trimmed = preg_replace('/^[^{\[]*/', '', $trimmed);
        $trimmed = preg_replace('/[^}\]]*$/', '', $trimmed);
        $trimmed = preg_replace('/,\s*([}\]])/', '$1', $trimmed);

        $candidates = [$trimmed];

        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $candidate = preg_replace('/,\s*([}\]])/', '$1', $match[0]);
            $candidates[] = $candidate;
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
