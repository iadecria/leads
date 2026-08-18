<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

class PublicFixtureDiscoveryService
{
    public function discoverByDate(string $date): array
    {
        $apiKey = config('openrouter.thesportsdb.api_key', '3');
        $baseUrl = rtrim(config('openrouter.thesportsdb.base_url'), '/');

        $response = Http::timeout(30)
            ->withOptions([
                'proxy' => '',
            ])
            ->get("{$baseUrl}/{$apiKey}/eventsday.php", [
            'd' => $date,
            's' => 'Soccer',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $events = $response->json('events') ?? [];

        return collect($events)->map(function (array $event) use ($date, $apiKey) {
            return [
                'event_id' => $event['idEvent'] ?? null,
                'league_id' => $event['idLeague'] ?? null,
                'date' => $event['dateEvent'] ?? $date,
                'home_team' => $event['strHomeTeam'] ?? null,
                'away_team' => $event['strAwayTeam'] ?? null,
                'competition' => $event['strLeague'] ?? null,
                'country' => $event['strCountry'] ?? null,
                'season' => $event['strSeason'] ?? null,
                'datetime' => isset($event['dateEvent'], $event['strTime']) ? ($event['dateEvent'].' '.$event['strTime']) : ($event['dateEvent'] ?? $date),
                'kickoff' => $event['strTime'] ?? null,
                'home_team_id' => $event['idHomeTeam'] ?? null,
                'away_team_id' => $event['idAwayTeam'] ?? null,
                'source_urls' => [
                    $event['strWebsite'] ?? "https://www.thesportsdb.com/api/v1/json/{$apiKey}/eventsday.php?d={$date}&s=Soccer",
                ],
                'source' => 'thesportsdb',
            ];
        })->all();
    }
}
