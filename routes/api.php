<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UserCardController;
use App\Http\Controllers\BlueprintController;

// ... (existing api routes, likely for user authentication)

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

// Route per la ricerca di Blueprint (da definire meglio il controller)
// Esempio:
// Route::get('blueprints/search', [BlueprintController::class, 'search'])->name('blueprints.search');
Route::get('blueprints/search', [BlueprintController::class, 'search'])->name('api.blueprints.search');

// Route per i dati aggregati della dashboard (valore portafoglio, ecc.)
// Questa potrebbe anche essere parte del DashboardController servito via web, ma se i dati sono richiesti via XHR:
// Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
