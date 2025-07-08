<?php

namespace App\Providers;

use App\Models\Blueprint;
use App\Observers\BlueprintObserver;
use App\Services\ElasticsearchService;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
