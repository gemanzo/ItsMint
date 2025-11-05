<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => function () use ($request) {
                    $user = $request->user();
                    // Only load roles if user exists and we need them for hasRole check
                    // Spatie Permission can work without eager loading, but loading here avoids N+1
                    return $user ? $user->load('roles') : null;
                },
            ],
            'oldest_portfolio_id' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                // Cache this calculation since it's used in multiple places
                return (int) ($user->portfolios()->oldest('created_at')->value('id') ?? 0);
            },
            'features' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                $feature = \Laravel\Pennant\Feature::for($user);
                return [
                    'is_admin' => $user->hasRole('admin'),
                    'portfolio_unlimited' => $feature->active('portfolio.unlimited'),
                    'search_advanced' => $feature->active('search.advanced'),
                    'export_csv' => $feature->active('export.csv'),
                    'analytics_portfolio' => $feature->active('analytics.portfolio'),
                    'alerts_price' => $feature->active('alerts.price'),
                ];
            },
            'ziggy' => fn() => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ]);
    }
}
