<?php

namespace App\Services\ApiFootball;

use App\Interfaces\Providers\FootballDataProviderInterface;
use App\Models\Competition;
use App\Models\CompetitionSeason;

class CompetitionSyncService
{
    private FootballDataProviderInterface $provider;

    public function __construct(FootballDataProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function sync(int $season): int
    {
        $leaguesResponse = $this->provider->getLeagues($season);
        $count = 0;

        foreach ($leaguesResponse as $item) {
            $leagueData = $item['league'];
            $countryData = $item['country'];
            $seasonsData = $item['seasons'];

            // Find the specified season data
            $targetSeasonData = null;
            foreach ($seasonsData as $s) {
                if ($s['year'] === $season) {
                    $targetSeasonData = $s;
                    break;
                }
            }

            if (! $targetSeasonData) {
                continue;
            }

            $competition = Competition::updateOrCreate(
                ['external_id' => $leagueData['id']],
                [
                    'name' => $leagueData['name'],
                    'type' => $leagueData['type'],
                    'logo' => $leagueData['logo'],
                    'country' => $countryData['name'],
                    // fas_enabled remains under system control, default true for new but not updated
                ]
            );

            CompetitionSeason::updateOrCreate(
                [
                    'competition_id' => $competition->id,
                    'season' => $season,
                ],
                [
                    'coverage' => $targetSeasonData['coverage'],
                    'is_current' => $targetSeasonData['current'],
                ]
            );

            $count++;
        }

        return $count;
    }
}
