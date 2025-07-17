<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use App\Models\CardScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

final class CardOcrService
{
    const TRIGRAM_THRESHOLD = 0.75;

    private ImageManager $imageManager;

    private ElasticsearchService $elasticsearchService;

    private GoogleVisionOcrService $googleVisionService;

    public function __construct(
        ElasticsearchService $elasticsearchService,
        GoogleVisionOcrService $googleVisionService
    ) {
        $this->imageManager = new ImageManager(new Driver);
        $this->elasticsearchService = $elasticsearchService;
        $this->googleVisionService = $googleVisionService;
    }

    /**
     * Process uploaded card image for OCR recognition
     */
    public function processCardImage(UploadedFile $file, int $userId, string $source = 'upload'): CardScan
    {
        // Create initial scan record
        $scan = CardScan::create([
            'user_id' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => '',
            'file_hash' => hash_file('sha256', $file->path()),
            'status' => 'pending',
            'source' => $source,
            'metadata' => [
                'original_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ],
        ]);

        try {
            // Create user directory if it doesn't exist
            $userDir = 'card-scans/'.$userId;
            if (! Storage::disk('public')->exists($userDir)) {
                Log::info('Creating user directory: '.$userDir);
                Storage::disk('public')->makeDirectory($userDir);
            }

            // Store the image using public disk
            $filename = 'card-scans/'.$userId.'/'.time().'_'.$scan->id.'.jpg';
            Log::info('Attempting to store file', ['filename' => $filename, 'user_id' => $userId]);

            $path = $file->storeAs('', $filename, 'public');
            Log::info('File stored successfully', ['path' => $path, 'filename' => $filename]);

            $scan->update(['file_path' => $filename]);

            // Verify file exists
            if (Storage::disk('public')->exists($filename)) {
                Log::info('File verified on disk', ['filename' => $filename]);
            } else {
                Log::error('File not found after storage', ['filename' => $filename]);
            }

            // Process the image
            $this->processImage($scan);

            return $scan;
        } catch (\Exception $e) {
            Log::error('Card OCR processing failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            $scan->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Process the stored image for OCR using Google Vision API
     */
    private function processImage(CardScan $scan): void
    {
        $scan->update(['status' => 'processing']);

        $fullPath = Storage::disk('public')->path($scan->file_path);

        try {
            // Check if Google Vision is available
            if (! $this->googleVisionService->isAvailable()) {
                throw new \Exception('Google Vision API is not properly configured');
            }

            // Preprocess image for better OCR (optional for Google Vision)
            $preprocessedPath = $this->preprocessImage($fullPath);

            // Perform OCR using Google Vision
            $ocrResult = $this->googleVisionService->extractTextFromImage($preprocessedPath);

            // Extract card information from Google Vision results
            $detectedCards = $this->extractCardInformationFromVision($ocrResult);

            // Calculate overall confidence based on matches found
            $overallConfidence = $this->calculateOverallConfidenceFromMatches($detectedCards, $ocrResult['confidence']);

            // Update scan with results
            $scan->update([
                'ocr_text' => $ocrResult['text'],
                'ocr_data' => $ocrResult,
                'detected_cards' => $detectedCards,
                'confidence_score' => $overallConfidence,
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            // Auto-assign blueprint if confidence is high enough
            $this->autoAssignBlueprint($scan);

            // Clean up preprocessed image
            if (file_exists($preprocessedPath) && $preprocessedPath !== $fullPath) {
                unlink($preprocessedPath);
            }
        } catch (\Exception $e) {
            Log::error('Google Vision OCR processing failed', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);

            $scan->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Preprocess image to improve OCR accuracy (lighter processing for Google Vision)
     */
    private function preprocessImage(string $imagePath): string
    {
        try {
            $image = $this->imageManager->read($imagePath);

            // Light preprocessing for Google Vision (it handles most optimization internally)
            // Resize only if too large (>2000px width)
            if ($image->width() > 2000) {
                $image->scale(2000);
            }

            // Save optimized image
            $preprocessedPath = sys_get_temp_dir().'/'.uniqid('gv_ocr_').'.jpg';
            $image->save($preprocessedPath, 90, 'jpg');

            Log::info('Image preprocessed for Google Vision', [
                'original_path' => $imagePath,
                'processed_size' => $image->width().'x'.$image->height(),
                'processed_path' => $preprocessedPath,
            ]);

            return $preprocessedPath;
        } catch (\Exception $e) {
            Log::warning('Image preprocessing failed, using original', ['error' => $e->getMessage()]);

            return $imagePath;
        }
    }

    /**
     * Extract card information from Google Vision results
     */
    private function extractCardInformationFromVision(array $visionResult): array
    {
        $text = $visionResult['text'];

        Log::info('=== OCR DEBUG: Starting new OCR matching ===', [
            'full_text' => $text,
            'text_length' => strlen($text),
        ]);

        // Clean and normalize the OCR text
        $cleanedOcrText = $this->normalizeOcrText($text);

        Log::info('=== OCR DEBUG: Cleaned OCR text ===', [
            'original_text' => $text,
            'cleaned_text' => $cleanedOcrText,
        ]);

        // Find matches using the new approach
        $matches = $this->findCardMatchesByOcrString($cleanedOcrText);

        Log::info('=== OCR DEBUG: Match results ===', [
            'matches_found' => count($matches),
            'matches' => $matches,
        ]);

        return $matches;
    }

    /**
     * Find card matches using PostgreSQL trigram similarity (direct approach)
     */
    private function findCardMatchesByOcrString(string $cleanedOcrText): array
    {
        $matches = [];

        Log::info('=== OCR DEBUG: Starting pure trigram similarity search ===', [
            'cleaned_text' => $cleanedOcrText,
            'text_length' => strlen($cleanedOcrText),
            'trigram_threshold' => self::TRIGRAM_THRESHOLD,
        ]);

        try {
            // Use PostgreSQL trigram similarity directly - much simpler!
            $candidates = Blueprint::selectRaw('
                id, name, expansion_id, image_url, ocr_match_string,
                similarity(ocr_match_string, ?) as trigram_score
            ', [$cleanedOcrText])
                ->whereNotNull('ocr_match_string')
                ->whereRaw('similarity(ocr_match_string, ?) >= ?', [$cleanedOcrText, self::TRIGRAM_THRESHOLD])
                ->with(['expansion', 'oneLogCards'])
                ->orderByRaw('similarity(ocr_match_string, ?) DESC', [$cleanedOcrText])
                ->limit(8) // Return top 8 matches only
                ->get();

            Log::info('=== OCR DEBUG: Pure trigram results ===', [
                'candidates_found' => count($candidates),
                'min_threshold' => self::TRIGRAM_THRESHOLD,
                'candidates' => $candidates->map(function ($candidate) {
                    return [
                        'id' => $candidate->id,
                        'name' => $candidate->name,
                        'trigram_score' => round($candidate->trigram_score * 100, 2),
                    ];
                })->toArray(),
            ]);

            // Convert candidates directly to matches (no additional processing needed)
            foreach ($candidates as $blueprint) {
                $matches[] = [
                    'blueprint_id' => $blueprint->id,
                    'name' => $blueprint->name,
                    'expansion' => $blueprint->expansion ? $blueprint->expansion->name : null,
                    'image_url' => $this->getProxiedImageUrl($blueprint->image_url),
                    'card_number' => $blueprint->collector_number,
                ];

                Log::info('=== OCR DEBUG: Trigram match added ===', [
                    'blueprint_id' => $blueprint->id,
                    'blueprint_name' => $blueprint->name,
                ]);
            }

            Log::info('=== OCR DEBUG: Final pure trigram results ===', [
                'total_matches' => count($matches),
                'performance' => 'Single PostgreSQL query - maximum efficiency!',
            ]);
        } catch (\Exception $e) {
            Log::error('=== OCR DEBUG: Trigram search failed ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to keyword-based filtering if trigram fails
            $matches = $this->fallbackKeywordMatching($cleanedOcrText);
        }

        return $matches;
    }

    /**
     * Fallback method using keyword extraction and LIKE queries if trigram fails
     */
    private function fallbackKeywordMatching(string $cleanedOcrText): array
    {
        Log::info('=== OCR DEBUG: Using fallback keyword matching ===');

        $keywords = $this->extractKeywords($cleanedOcrText);
        $matches = [];

        if (empty($keywords)) {
            return [];
        }

        // Fallback with lower trigram threshold (70%) since keywords may be partial
        $fallbackTrigramScore = 0.70;

        $candidates = Blueprint::selectRaw('
                id, name, expansion_id, image_url, ocr_match_string,
                similarity(ocr_match_string, ?) as trigram_score
            ', [$cleanedOcrText])
            ->whereNotNull('ocr_match_string')
            ->whereRaw('similarity(ocr_match_string, ?) >= ?', [$cleanedOcrText, $fallbackTrigramScore])
            ->with(['expansion', 'oneLogCards'])
            ->orderByRaw('similarity(ocr_match_string, ?) DESC', [$cleanedOcrText])
            ->limit(3)
            ->get();

        Log::info('=== OCR DEBUG: Keyword fallback with trigram ===', [
            'keywords_used' => $keywords,
            'fallback_threshold' => $fallbackTrigramScore,
            'candidates_found' => count($candidates),
        ]);

        // Convert candidates directly to matches
        foreach ($candidates as $blueprint) {
            $matches[] = [
                'blueprint_id' => $blueprint->id,
                'name' => $blueprint->name,
                'expansion' => $blueprint->expansion ? $blueprint->expansion->name : null,
                'image_url' => $this->getProxiedImageUrl($blueprint->image_url),
                'card_number' => $blueprint->collector_number,
            ];
        }

        return $matches;
    }

    /**
     * Extract meaningful keywords from OCR text for fallback matching
     */
    private function extractKeywords(string $text): array
    {
        // Extract card names, serials, and other meaningful terms
        $keywords = [];

        // Extract potential card names (capitalized words)
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $nameMatches);
        if (! empty($nameMatches[0])) {
            $keywords = array_merge($keywords, $nameMatches[0]);
        }

        // Extract potential serial numbers (OP##-###, ST##-### etc.)
        preg_match_all('/\b[A-Z]{2,3}\d{2}-?\d{3}\b/', $text, $serialMatches);
        if (! empty($serialMatches[0])) {
            $keywords = array_merge($keywords, $serialMatches[0]);
        }

        // Extract other meaningful words (length >= 4)
        preg_match_all('/\b\w{4,}\b/', $text, $wordMatches);
        if (! empty($wordMatches[0])) {
            $keywords = array_merge($keywords, $wordMatches[0]);
        }

        // Remove duplicates and filter
        $keywords = array_unique($keywords);
        $keywords = array_filter($keywords, function ($keyword) {
            return strlen($keyword) >= 3 && ! in_array(strtolower($keyword), ['the', 'and', 'for', 'you', 'can', 'may', 'all']);
        });

        return array_values($keywords);
    }

    /**
     * Normalize OCR text for matching
     */
    private function normalizeOcrText(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove special characters except spaces
        $text = preg_replace('/[^\w\s]/u', ' ', $text);

        // Normalize multiple spaces to single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim whitespace
        $text = trim($text);

        return $text;
    }

    /**
     * Get proxied image URL to avoid CORS issues
     */
    private function getProxiedImageUrl(?string $originalUrl): ?string
    {
        if (! $originalUrl) {
            return null;
        }

        // If it's a CardTrader image, use our proxy
        if (str_starts_with($originalUrl, 'https://cardtrader.com/uploads/')) {
            return url('/api/image-proxy?url='.urlencode($originalUrl));
        }

        // For other images, return as-is
        return $originalUrl;
    }

    /**
     * Calculate OCR confidence based on matches found
     */
    private function calculateOcrConfidence(array $detectedCards): float
    {
        if (empty($detectedCards)) {
            return 0.0;
        }

        // Since we removed confidence scores, use number of matches as confidence indicator
        // More matches = higher confidence (up to 100%)
        $matchCount = count($detectedCards);
        $confidence = min(100.0, $matchCount * 25.0); // 1 match = 25%, 4+ matches = 100%

        return $confidence;
    }

    /**
     * Calculate overall confidence based on detected cards and Google Vision confidence
     */
    private function calculateOverallConfidenceFromMatches(array $detectedCards, float $googleVisionConfidence): float
    {
        $ocrConfidence = $this->calculateOcrConfidence($detectedCards);
        $overallConfidence = ($ocrConfidence + $googleVisionConfidence) / 2;

        return round($overallConfidence, 2);
    }

    /**
     * Auto-assign blueprint if we have matches and good Google Vision confidence
     */
    private function autoAssignBlueprint(CardScan $scan): void
    {
        if (empty($scan->detected_cards)) {
            return;
        }

        // Auto-assign the first match if we have good Google Vision confidence
        $bestMatch = $scan->detected_cards[0] ?? null;

        if (
            $bestMatch &&
            $scan->google_vision_confidence >= 80.0 &&
            count($scan->detected_cards) >= 1
        ) {
            $scan->update(['blueprint_id' => $bestMatch['blueprint_id']]);
        }
    }

    /**
     * Get scan results with blueprint information
     */
    public function getScanWithDetails(int $scanId, int $userId): ?CardScan
    {
        return CardScan::with(['blueprint.expansion', 'blueprint.latestPrice'])
            ->where('id', $scanId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Retry failed scan
     */
    public function retryScan(CardScan $scan): CardScan
    {
        if (! $scan->isFailed()) {
            throw new \InvalidArgumentException('Can only retry failed scans');
        }

        $scan->update([
            'status' => 'pending',
            'error_message' => null,
            'processed_at' => null,
        ]);

        $this->processImage($scan);

        return $scan;
    }
}
