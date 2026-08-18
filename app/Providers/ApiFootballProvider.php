<?php

namespace App\Providers;

use App\Interfaces\Providers\FootballDataProviderInterface;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\ApiFootball\ApiFootballProviderService;
use App\Services\ApiFootball\SportMonksProviderService;
use Illuminate\Support\ServiceProvider;

class ApiFootballProvider extends ServiceProvider
{
    private ?ApiFootballClient $client = null;

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FootballDataProviderInterface::class, function ($app) {
            return config('api-football.provider') === 'sportmonks'
                ? $app->make(SportMonksProviderService::class)
                : $app->make(ApiFootballProviderService::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

}
