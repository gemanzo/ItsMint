<?php

namespace App\Providers;

use App\Models\Blueprint;
use App\Observers\BlueprintObserver;
use Illuminate\Support\Facades\Vite;
use App\Services\ElasticsearchService;
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
        Vite::prefetch(3);

        Blueprint::observe(BlueprintObserver::class);
    }
}
