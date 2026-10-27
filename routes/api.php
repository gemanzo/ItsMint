<?php

use App\Http\Controllers\Api\BlueprintController;
use App\Http\Controllers\Api\BlueprintSearchController;
use App\Http\Controllers\Api\CardScanController;
use App\Http\Controllers\Api\ImageProxyController;
use App\Http\Controllers\Api\PortfolioTradeController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UserCardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Blueprint pricing endpoints
    Route::get('/blueprints/{blueprint}/price-history', [BlueprintController::class, 'priceHistory'])->name('api.blueprints.price-history');
    Route::get('/blueprints/{blueprint}/prices/by-language', [BlueprintController::class, 'currentPricesByLanguage'])->name('api.blueprints.prices.by-language');
    Route::get('/blueprints/{blueprint}/prices/cheapest', [BlueprintController::class, 'cheapestPrices'])->name('api.blueprints.prices.cheapest');
    Route::get('/blueprints/{blueprint}/prices/statistics', [BlueprintController::class, 'priceStatistics'])->name('api.blueprints.prices.statistics');
    Route::get('/blueprints/{blueprint}/market-statistics', [BlueprintController::class, 'marketStatistics'])->name('api.blueprints.market-statistics');

    // Portfolio card limit check - MUST be before portfolios/{portfolio} routes
    Route::get('/portfolios/{portfolio}/card-limit', [PortfolioController::class, 'checkCardLimit'])->name('api.portfolios.checkCardLimit');

    // Portfolio blueprint operations
    Route::post('/portfolios/{portfolio}/blueprints', [PortfolioController::class, 'addBlueprint'])->name('api.portfolios.addBlueprint');
    Route::delete('/portfolios/{portfolio}/blueprints', [PortfolioController::class, 'removeBlueprint'])->name('api.portfolios.removeBlueprint');

    // Portfolio value history endpoint
    Route::get('/portfolios/{portfolio}/value-history', [PortfolioController::class, 'valueHistory'])
        ->middleware('ensure.feature:analytics.portfolio')
        ->name('api.portfolios.value-history');

    // Other endpoints
    Route::post('/card-scan', [CardScanController::class, 'scan']);
    Route::apiResource('portfolios', PortfolioController::class);

    // Ledger & Sales endpoints
    Route::post('/portfolios/{portfolio}/sell', [PortfolioTradeController::class, 'sell'])->name('api.portfolios.sell');
    Route::get('/portfolios/{portfolio}/sales', [PortfolioTradeController::class, 'salesIndex'])->name('api.portfolios.sales.index');
    Route::put('/portfolios/{portfolio}/sales/{sale}', [PortfolioTradeController::class, 'salesUpdate'])->name('api.portfolios.sales.update');
    Route::delete('/portfolios/{portfolio}/sales/{sale}', [PortfolioTradeController::class, 'salesDestroy'])->name('api.portfolios.sales.destroy');
    Route::get('/portfolios/{portfolio}/cash-history', [PortfolioTradeController::class, 'cashHistory'])->name('api.portfolios.cash-history');
    Route::get('/portfolios/{portfolio}/piggy-bank/metrics', [PortfolioTradeController::class, 'piggyBankMetrics'])->name('api.portfolios.piggy-bank.metrics');
    Route::post('/portfolios/{portfolio}/transactions', [PortfolioTradeController::class, 'transactionsStore'])->name('api.portfolios.transactions.store');
    Route::get('/portfolios/{portfolio}/sales/export', [PortfolioTradeController::class, 'salesExport'])
        ->middleware('ensure.feature:export.csv')
        ->name('api.portfolios.sales.export');
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

// Card Scan routes
Route::prefix('card-scans')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('upload', [CardScanController::class, 'upload'])->name('api.card-scans.upload');
    Route::get('/', [CardScanController::class, 'index'])->name('api.card-scans.index');
    Route::get('{id}', [CardScanController::class, 'show'])->name('api.card-scans.show');
    Route::post('{id}/retry', [CardScanController::class, 'retry'])->name('api.card-scans.retry');
    Route::put('{id}/blueprint', [CardScanController::class, 'updateBlueprint'])->name('api.card-scans.update-blueprint');
    Route::delete('{id}', [CardScanController::class, 'destroy'])->name('api.card-scans.destroy');
    Route::get('{id}/image', [CardScanController::class, 'image'])->name('api.card-scans.image');
    Route::post('{id}/create-user-card', [CardScanController::class, 'createUserCard'])->name('api.card-scans.create-user-card');
});

// NEW: Elasticsearch-powered Blueprint search routes
Route::prefix('blueprints')->middleware(['auth:sanctum', \App\Http\Middleware\ElasticsearchFallback::class])->group(function () {
    // Base search: sempre disponibile (Free)
    Route::get('search', [BlueprintSearchController::class, 'search'])->name('api.blueprints.search');

    // Suggest/autocomplete: Premium (parte della ricerca avanzata)
    Route::get('suggestions', [BlueprintSearchController::class, 'suggestions'])
        ->middleware('ensure.feature:search.advanced')
        ->name('api.blueprints.suggestions');

    // Stats: lasciamo libero
    Route::get('search/stats', [BlueprintSearchController::class, 'stats'])->name('api.blueprints.search.stats');

    Route::get('{blueprint}', [BlueprintController::class, 'show'])->name('api.blueprints.show');
});

// DEPRECATED: Old database-based search (keep for backward compatibility during transition)
// Route::get('blueprints/search', [BlueprintController::class, 'search'])->name('api.blueprints.search.legacy');

// Image Proxy for CardTrader images.
// Public on purpose (images are loaded by <img> tags without API authentication),
// so it is rate limited and the controller only accepts CardTrader upload URLs.
Route::get('image-proxy', [ImageProxyController::class, 'proxy'])
    ->middleware('throttle:image-proxy')
    ->name('api.image-proxy');

// Route per i dati aggregati della dashboard (valore portafoglio, ecc.)
// Questa potrebbe anche essere parte del DashboardController servito via web, ma se i dati sono richiesti via XHR:
// Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
