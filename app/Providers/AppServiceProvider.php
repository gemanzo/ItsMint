<?php

namespace App\Providers;

use App\Models\Blueprint;
use App\Observers\BlueprintObserver;
use App\Services\BlueprintPriceService;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\Providers\CardmarketGuidePriceProvider;
use App\Services\Pricing\Providers\CardmarketGuideReader;
use App\Services\Pricing\Providers\CardTraderPriceProvider;
use App\Services\ElasticsearchService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ElasticsearchService::class);

        // Pricing providers wiring
        $this->app->singleton(CardmarketGuideReader::class, fn() => new CardmarketGuideReader());
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
    }
}
