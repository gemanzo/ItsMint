<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use Illuminate\Http\JsonResponse;

final class BlueprintController extends Controller
{
    public function priceHistory(Blueprint $blueprint): JsonResponse
    {
        $priceHistory = $blueprint->prices()->orderBy('fetched_at', 'asc')->get();

        return response()->json($priceHistory);
    }
}
