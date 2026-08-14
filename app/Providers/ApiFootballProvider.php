<?php

namespace App\Providers;

use App\Interfaces\Providers\FootballDataProviderInterface;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\ApiFootball\ApiFootballProviderService;
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
            return $this; // Return itself or a dedicated wrapper. Usually we bind a specific class, but here we'll let the provider act as the implementation for simplicity or bind a dedicated service.
        });

        // Better: bind a dedicated class. Let's create an anonymous class or just bind ApiFootballProvider implementation directly.
        $this->app->singleton(FootballDataProviderInterface::class, ApiFootballProviderService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    // Note: To properly adhere to Laravel architecture, we should have a Service class implementing the interface,
    // and this Provider just binds it. I will create ApiFootballProviderService separately and use it here.
}
