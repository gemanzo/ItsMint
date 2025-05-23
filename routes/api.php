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

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('portfolios', PortfolioController::class);
    Route::post('portfolios/{portfolio}/user-cards', [PortfolioController::class, 'addUserCard'])->name('portfolios.addUserCard');
    Route::delete('portfolios/{portfolio}/user-cards/{userCard}', [PortfolioController::class, 'removeUserCard'])->name('portfolios.removeUserCard');
    Route::put('portfolios/{portfolio}/user-cards/{userCard}', [PortfolioController::class, 'updateUserCardQuantity'])->name('portfolios.updateUserCardQuantity');

    Route::apiResource('user-cards', UserCardController::class);

    // Route per la ricerca di Blueprint (da definire meglio il controller)
    // Esempio:
    // Route::get('blueprints/search', [BlueprintController::class, 'search'])->name('blueprints.search');
    Route::get('blueprints/search', [BlueprintController::class, 'search'])->name('api.blueprints.search');

    // Route per i dati aggregati della dashboard (valore portafoglio, ecc.)
    // Questa potrebbe anche essere parte del DashboardController servito via web, ma se i dati sono richiesti via XHR:
    // Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
});
