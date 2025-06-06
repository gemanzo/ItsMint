<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CardOcrService;
use App\Models\CardScan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

final class CardScanController extends Controller
{
    public function __construct(
        private readonly CardOcrService $cardOcrService
    ) {}

    /**
     * Upload and process a card image for OCR
     */
    public function upload(Request $request): JsonResponse
    {
        $ocrConfig = config('ocr');

        $validated = $request->validate([
            'image' => [
                'required',
                File::image()
                    ->max($ocrConfig['upload']['max_file_size'])
                    ->dimensions(
                        Rule::dimensions()
                            ->minWidth($ocrConfig['upload']['min_width'])
                            ->minHeight($ocrConfig['upload']['min_height'])
                    )
            ],
            'source' => 'sometimes|string|in:' . implode(',', $ocrConfig['upload']['allowed_sources']),
        ]);

        try {
            $source = $validated['source'] ?? 'upload';
            $scan = $this->cardOcrService->processCardImage(
                $request->file('image'),
                Auth::id(),
                $source
            );

            return response()->json([
                'success' => true,
                'scan' => $scan->load(['blueprint.expansion', 'blueprint.latestPrice']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's scan history
     */
    public function index(Request $request): JsonResponse
    {
        $ocrConfig = config('ocr');

        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,processing,completed,failed',
            'per_page' => 'sometimes|integer|min:1|max:' . $ocrConfig['pagination']['max_per_page'],
        ]);

        $query = CardScan::with(['blueprint.expansion', 'blueprint.latestPrice'])
            ->where('user_id', Auth::id())
            ->latest();

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = $validated['per_page'] ?? $ocrConfig['pagination']['default_per_page'];
        $scans = $query->paginate($perPage);

        return response()->json($scans);
    }

    /**
     * Get specific scan details
     */
    public function show(int $id): JsonResponse
    {
        $scan = $this->cardOcrService->getScanWithDetails($id, Auth::id());

        if (!$scan) {
            return response()->json([
                'success' => false,
                'message' => 'Scan not found or unauthorized',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'scan' => $scan,
        ]);
    }

    /**
     * Retry a failed scan
     */
    public function retry(int $id): JsonResponse
    {
        $scan = CardScan::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$scan) {
            return response()->json([
                'success' => false,
                'message' => 'Scan not found or unauthorized',
            ], 404);
        }

        if (!$scan->isFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only retry failed scans',
            ], 400);
        }

        try {
            $retryedScan = $this->cardOcrService->retryScan($scan);

            return response()->json([
                'success' => true,
                'scan' => $retryedScan->load(['blueprint.expansion', 'blueprint.latestPrice']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update scan blueprint assignment
     */
    public function updateBlueprint(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
        ]);

        $scan = CardScan::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$scan) {
            return response()->json([
                'success' => false,
                'message' => 'Scan not found or unauthorized',
            ], 404);
        }

        $scan->update(['blueprint_id' => $validated['blueprint_id']]);

        return response()->json([
            'success' => true,
            'scan' => $scan->load(['blueprint.expansion', 'blueprint.latestPrice']),
        ]);
    }

    /**
     * Delete a scan
     */
    public function destroy(int $id): JsonResponse
    {
        $scan = CardScan::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$scan) {
            return response()->json([
                'success' => false,
                'message' => 'Scan not found or unauthorized',
            ], 404);
        }

        // Delete associated image file
        if ($scan->file_path && Storage::disk('public')->exists($scan->file_path)) {
            Storage::disk('public')->delete($scan->file_path);
        }

        $scan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scan deleted successfully',
        ]);
    }

    /**
     * Get scan image
     */
    public function image(int $id)
    {
        $scan = CardScan::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$scan || !$scan->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found',
            ], 404);
        }

        // Check if file exists on public disk
        if (!Storage::disk('public')->exists($scan->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Image file not found on disk',
            ], 404);
        }

        // Return the image file directly
        $file = Storage::disk('public')->get($scan->file_path);
        $mimeType = Storage::disk('public')->mimeType($scan->file_path);

        return response($file, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($scan->file_path) . '"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Create user card from scan
     */
    public function createUserCard(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'condition' => 'required|string|max:50',
            'language' => 'required|string|max:10',
            'quantity' => 'sometimes|integer|min:1|max:50',
            'purchase_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $scan = CardScan::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('blueprint')
            ->first();

        if (!$scan) {
            return response()->json([
                'success' => false,
                'message' => 'Scan not found or unauthorized',
            ], 404);
        }

        if (!$scan->blueprint_id) {
            return response()->json([
                'success' => false,
                'message' => 'No blueprint assigned to this scan',
            ], 400);
        }

        try {
            $userCardData = [
                'blueprint_id' => $scan->blueprint_id,
                'condition' => $validated['condition'],
                'language' => $validated['language'],
                'quantity' => $validated['quantity'] ?? 1,
                'purchase_price' => $validated['purchase_price'],
                'notes' => $validated['notes'] ?? "Created from scan #{$scan->id}",
            ];

            // Use existing UserCardController logic
            $userCardController = new \App\Http\Controllers\UserCardController();
            $request->merge($userCardData);

            $response = $userCardController->store($request);
            $responseData = json_decode($response->getContent(), true);

            return response()->json([
                'success' => true,
                'user_card' => $responseData,
                'scan' => $scan,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user card: ' . $e->getMessage(),
            ], 500);
        }
    }
}
