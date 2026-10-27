<?php

namespace App\Providers;

use App\Models\Blueprint;
use App\Observers\BlueprintObserver;
use App\Services\BlueprintPriceService;
use App\Services\ElasticsearchService;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\Providers\CardmarketGuidePriceProvider;
use App\Services\Pricing\Providers\CardmarketGuideReader;
use App\Services\Pricing\Providers\CardTraderPriceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ElasticsearchService::class);

        // Pricing providers wiring
        $this->app->singleton(CardmarketGuideReader::class, fn () => new CardmarketGuideReader);
        $this->app->singleton(CardmarketGuidePriceProvider::class, function ($app) {
            return new CardmarketGuidePriceProvider($app->make(CardmarketGuideReader::class));
        });

        // Wrap existing CardTrader service as a provider
        $this->app->singleton(CardTraderPriceProvider::class, function ($app) {
            return new CardTraderPriceProvider($app->make(BlueprintPriceService::class));
        });

        $this->app->singleton(PriceAggregatorService::class, function ($app) {
            $providers = [
                $app->make(CardmarketGuidePriceProvider::class),
                $app->make(CardTraderPriceProvider::class),
            ];

            return new PriceAggregatorService($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure rate limiter for CardTrader API to avoid getting blocked
        RateLimiter::for('cardtrader-api', function () {
            return Limit::perMinute(30)->by('cardtrader-api');
        });

        // Default limit for every /api route: per user when authenticated, per IP otherwise.
        // Generous enough for search-as-you-type, low enough to stop scripted abuse.
        RateLimiter::for('api', function (Request $request) {
            $rateLimitKey = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(120)->by($rateLimitKey);
        });

        // The image proxy is public and downloads remote content: limit it per client IP
        RateLimiter::for('image-proxy', function (Request $request) {
            return Limit::perMinute(config('ocr.image_proxy.requests_per_minute'))->by($request->ip());
        });

        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');

            // Force HTTPS for all URLs when behind a proxy
            $this->app['request']->server->set('HTTPS', 'on');
            $this->app['request']->server->set('SERVER_PORT', 443);
            $this->app['request']->server->set('HTTP_X_FORWARDED_PROTO', 'https');
        }

        Vite::prefetch(3);

        Blueprint::observe(BlueprintObserver::class);

        // Define feature flags for premium subscription
        $this->defineFeatures();
    }

    /**
     * Define all feature flags using Laravel Pennant.
     */
    private function defineFeatures(): void
    {
        // Portfolio unlimited: Premium users can have unlimited portfolios
        Feature::define('portfolio.unlimited', function ($user) {
            return $user->subscribed('premium') || $user->hasRole('admin');
        });

        // Search advanced: Premium users get advanced search with filters and more results
        Feature::define('search.advanced', function ($user) {
            return $user->subscribed('premium') || $user->hasRole('admin');
        });

        // Export CSV: Premium users can export their sales data
        Feature::define('export.csv', function ($user) {
            return $user->subscribed('premium') || $user->hasRole('admin');
        });

        // Analytics portfolio: Premium users get portfolio performance analytics
        Feature::define('analytics.portfolio', function ($user) {
            return $user->subscribed('premium') || $user->hasRole('admin');
        });

        // Price alerts: Premium users get price change notifications
        Feature::define('alerts.price', function ($user) {
            return $user->subscribed('premium') || $user->hasRole('admin');
        });
    }
}
