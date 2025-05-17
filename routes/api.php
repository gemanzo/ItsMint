<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ... (existing api routes, likely for user authentication)

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Removed route for live price, as it's handled by background jobs now
// Route::get('/blueprints/{blueprint_ct_id}/price', [CardPriceController::class, 'fetchLivePrice'])
//     ->middleware('auth:sanctum') 
//     ->name('api.blueprints.price');
