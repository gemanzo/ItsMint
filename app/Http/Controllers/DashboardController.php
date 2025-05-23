<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Esempio di dati che potremmo voler passare subito:
        // $user = $request->user();
        // $portfolios = $user->portfolios()->withCount('userCards')->get();
        // $totalValue = 0; // Calcolo da implementare

        return Inertia::render('Dashboard/Index', [
            // 'portfolios' => $portfolios,
            // 'totalPortfolioValue' => $totalValue,
            // 'userName' => $user->name,
        ]);
    }

    // public function summary(Request $request)
    // {
    //     // Logica per calcolare il valore totale del portafoglio e la variazione
    //     // Restituire JSON
    // }
}
