<?php

namespace App\Services\ApiFootball;

use App\Exceptions\ApiFootball\ApiFootballAuthenticationException;
use App\Exceptions\ApiFootball\ApiFootballException;
use App\Exceptions\ApiFootball\ApiFootballRateLimitException;
use App\Models\ApiRequestLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiFootballClient
{
    private ApiFootballCacheService $cache;

    public function __construct(ApiFootballCacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Make a GET request to the API-Football v3.
     */
    public function get(string $endpoint, array $parameters = [], bool $force = false): array
    {
        // 1. Check Cache
        if (! $force) {
            $cached = $this->cache->get($endpoint, $parameters);
            if ($cached) {
                $this->logRequest($endpoint, true);

                return $cached;
            }
        }

        // 2. Build Request
        $baseUrl = config('api-football.base_url');
        $key = config('api-football.key');
        $timeout = config('api-football.timeout', 15);

        if (app()->environment('local') && $key === 'testing-key') {
            $this->logRequest($endpoint, true);

            return [];
        }

        if (empty($key)) {
            throw new ApiFootballAuthenticationException('API-Football Key is not configured.');
        }

        $url = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');

        try {
            // 3. Execute Request
            $response = Http::withHeaders([
                'x-apisports-key' => $key,
            ])
                ->timeout($timeout)
                ->retry(3, 1000) // Conservative retry
                ->get($url, $parameters);

            $this->logRequest($endpoint, false);

            // 4. Handle Response Errors
            if ($response->status() === 401 || $response->status() === 403) {
                throw new ApiFootballAuthenticationException('Authentication failed: '.$response->body());
            }

            if ($response->status() === 429) {
                throw new ApiFootballRateLimitException('Rate limit exceeded: '.$response->body());
            }

            if (! $response->successful()) {
                throw new ApiFootballException('API request failed with status '.$response->status().': '.$response->body());
            }

            $data = $response->json();

            // Check API-level errors first
            if (isset($data['errors']) && ! empty($data['errors'])) {
                $errorStr = is_array($data['errors']) ? json_encode($data['errors']) : $data['errors'];

                // Sometimes auth errors come as 200 with errors array
                if (isset($data['errors']['token'])) {
                    throw new ApiFootballAuthenticationException('Authentication error: '.$errorStr);
                }

                if (isset($data['errors']['requests'])) {
                    throw new ApiFootballRateLimitException('Rate limit error: '.$errorStr);
                }

                throw new ApiFootballException('API returned errors: '.$errorStr);
            }

            if (! isset($data['response'])) {
                throw new ApiFootballException('Invalid JSON response or missing "response" key: '.$response->body());
            }

            // 5. Cache Response
            $ttl = $this->cache->determineTtl($endpoint, $parameters);
            $this->cache->put($endpoint, $parameters, $data['response'], $ttl);

            return $data['response'];

        } catch (ConnectionException $e) {
            throw new ApiFootballException('Connection timeout or error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Log request for consumption tracking.
     */
    private function logRequest(string $endpoint, bool $isCacheHit): void
    {
        // Extract base endpoint (e.g., 'fixtures' from 'fixtures?date=...')
        $baseEndpoint = explode('?', $endpoint)[0];

        $log = ApiRequestLog::firstOrCreate(
            ['endpoint' => $baseEndpoint],
            ['request_count' => 0, 'cache_hits' => 0, 'cache_misses' => 0]
        );

        $log->request_count++;
        $log->last_request_at = now();

        if ($isCacheHit) {
            $log->cache_hits++;
        } else {
            $log->cache_misses++;
        }

        $log->save();
    }
}
