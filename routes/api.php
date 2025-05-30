<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UserCardController;
use App\Http\Controllers\BlueprintController;
use App\Http\Controllers\Api\BlueprintSearchController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Removed route for live price, as it's handled by background jobs now
// Route::get('/blueprints/{blueprint_ct_id}/price', [CardPriceController::class, 'fetchLivePrice'])
//     ->middleware('auth:sanctum') 
//     ->name('api.blueprints.price');

Route::apiResource('portfolios', PortfolioController::class)->middleware('auth:sanctum')->names([
    'index' => 'api.portfolios.index',
    'store' => 'api.portfolios.store',
    'show' => 'api.portfolios.show',
    'update' => 'api.portfolios.update',
    'destroy' => 'api.portfolios.destroy',
]);
Route::post('portfolios/{portfolio}/user-cards', [PortfolioController::class, 'addUserCard'])->middleware('auth:sanctum')->name('api.portfolios.addUserCard');
Route::delete('portfolios/{portfolio}/user-cards/{userCard}', [PortfolioController::class, 'removeUserCard'])->middleware('auth:sanctum')->name('api.portfolios.removeUserCard');
Route::put('portfolios/{portfolio}/user-cards/{userCard}', [PortfolioController::class, 'updateUserCardQuantity'])->middleware('auth:sanctum')->name('api.portfolios.updateUserCardQuantity');

Route::apiResource('user-cards', UserCardController::class)->middleware('auth:sanctum')->names([
    'index' => 'api.user-cards.index',
    'store' => 'api.user-cards.store',
    'show' => 'api.user-cards.show',
    'update' => 'api.user-cards.update',
    'destroy' => 'api.user-cards.destroy',
]);

// NEW: Elasticsearch-powered Blueprint search routes
Route::prefix('blueprints')->middleware([\App\Http\Middleware\ElasticsearchFallback::class])->group(function () {
    Route::get('search', [BlueprintSearchController::class, 'search'])->name('api.blueprints.search');
    Route::get('suggestions', [BlueprintSearchController::class, 'suggestions'])->name('api.blueprints.suggestions');
    Route::get('search/stats', [BlueprintSearchController::class, 'stats'])->name('api.blueprints.search.stats');
});

// DEPRECATED: Old database-based search (keep for backward compatibility during transition)
// Route::get('blueprints/search', [BlueprintController::class, 'search'])->name('api.blueprints.search.legacy');

// Route per i dati aggregati della dashboard (valore portafoglio, ecc.)
// Questa potrebbe anche essere parte del DashboardController servito via web, ma se i dati sono richiesti via XHR:
// Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
