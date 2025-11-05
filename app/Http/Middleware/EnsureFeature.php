<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

final class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();

        if (! $user || ! Feature::for($user)->active($feature)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Feature not available for your plan',
                    'feature' => $feature,
                ], 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
