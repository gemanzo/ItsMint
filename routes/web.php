<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BlueprintController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Blueprint routes
    Route::prefix('dashboard/blueprints')->name('dashboard.blueprints.')->group(function () {
        Route::get('/', [BlueprintController::class, 'index'])
            ->middleware(['verified'])
            ->name('index'); // Will be dashboard.blueprints.index
        Route::get('/{blueprint}', [BlueprintController::class, 'show'])
            ->middleware(['verified'])
            ->name('show'); // Will be dashboard.blueprints.show
    });
});

require __DIR__ . '/auth.php';
