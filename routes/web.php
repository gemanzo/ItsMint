<?php

use App\Http\Controllers\BlueprintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortfolioController as WebPortfolioController;
use App\Http\Controllers\ProfileController;
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Card Scanner route
    Route::get('/card-scanner', function () {
        return Inertia::render('CardScan/Index');
    })->name('card-scanner');

    // Blueprint routes
    Route::prefix('blueprints')->name('blueprints.')->group(function () {
        Route::get('/', [BlueprintController::class, 'index'])
            ->middleware(['verified'])
            ->name('index'); // Will be blueprints.index
        Route::get('/{blueprint}', [BlueprintController::class, 'show'])
            ->middleware(['verified'])
            ->name('show'); // Will be blueprints.show
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Portfolio Web Routes
    Route::get('/portfolios', [WebPortfolioController::class, 'index'])->name('portfolios.index');
    Route::get('/portfolios/{portfolio}', [WebPortfolioController::class, 'show'])->name('portfolios.show');
});

require __DIR__ . '/auth.php';
