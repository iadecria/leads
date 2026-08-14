<?php

namespace App\Services\ApiFootball;

use App\Models\ApiCache;

class ApiFootballCacheService
{
    /**
     * Get cached response if valid and enabled.
     */
    public function get(string $endpoint, array $parameters = [], bool $force = false): ?array
    {
        if (! config('api-football.cache_enabled') || $force) {
            return null;
        }

        $key = $this->generateKey($endpoint, $parameters);

        $cache = ApiCache::where('cache_key', $key)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($cache) {
            return $cache->response;
        }

        return null;
    }

    /**
     * Save response to cache.
     */
    public function put(string $endpoint, array $parameters, array $response, ?int $ttlMinutes = null): void
    {
        if (! config('api-football.cache_enabled')) {
            return;
        }

        $key = $this->generateKey($endpoint, $parameters);

        $expiresAt = $ttlMinutes ? now()->addMinutes($ttlMinutes) : null;

        ApiCache::updateOrCreate(
            ['cache_key' => $key],
            [
                'endpoint' => $endpoint,
                'parameters_hash' => md5(json_encode($parameters)),
                'response' => $response,
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Generate a unique cache key based on endpoint and parameters.
     */
    public function generateKey(string $endpoint, array $parameters): string
    {
        ksort($parameters);
        $paramString = http_build_query($parameters);

        $key = $endpoint;
        if (! empty($paramString)) {
            $key .= ':'.$paramString;
        }

        return $key;
    }

    /**
     * Determine TTL based on endpoint and parameters.
     */
    public function determineTtl(string $endpoint, array $parameters = []): ?int
    {
        if (str_starts_with($endpoint, 'leagues')) {
            return 24 * 60; // 24 hours
        }

        if (str_starts_with($endpoint, 'standings')) {
            return 6 * 60; // 6 hours
        }

        if (str_starts_with($endpoint, 'fixtures')) {
            // Check if it's for a specific date
            if (isset($parameters['date'])) {
                $date = $parameters['date'];
                if ($date === now()->format('Y-m-d')) {
                    return 15; // 15 minutes for today's fixtures
                } elseif ($date > now()->format('Y-m-d')) {
                    return 60; // 1 hour for future fixtures
                } else {
                    return 24 * 60; // 24 hours for past fixtures (though usually permanent)
                }
            }

            // Check if checking a specific fixture
            if (isset($parameters['id'])) {
                // We don't have status here easily, but default to short for active checking
                return 15;
            }

            return 60; // Default 1 hour
        }

        return 60; // Default 1 hour for others
    }
}
