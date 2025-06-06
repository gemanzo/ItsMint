<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use App\Models\CardScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use thiagoalessio\TesseractOCR\TesseractOCR;

final class CardOcrService
{
    private readonly ImageManager $imageManager;
    private readonly ElasticsearchService $elasticsearchService;

    public function __construct(ElasticsearchService $elasticsearchService)
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->elasticsearchService = $elasticsearchService;
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
            $userDir = 'card-scans/' . $userId;
            if (!Storage::disk('public')->exists($userDir)) {
                Log::info('Creating user directory: ' . $userDir);
                Storage::disk('public')->makeDirectory($userDir);
            }

            // Store the image using public disk
            $filename = 'card-scans/' . $userId . '/' . time() . '_' . $scan->id . '.jpg';
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
     * Process the stored image for OCR
     */
    private function processImage(CardScan $scan): void
    {
        $scan->update(['status' => 'processing']);

        $fullPath = Storage::disk('public')->path($scan->file_path);

        // Preprocess image for better OCR
        $preprocessedPath = $this->preprocessImage($fullPath);

        // Perform OCR
        $ocrResult = $this->performOcr($preprocessedPath);

        // Extract card information
        $detectedCards = $this->extractCardInformation($ocrResult['text']);

        // Update scan with results
        $scan->update([
            'ocr_text' => $ocrResult['text'],
            'ocr_data' => $ocrResult,
            'detected_cards' => $detectedCards,
            'confidence_score' => $this->calculateOverallConfidence($detectedCards),
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        // Auto-assign blueprint if confidence is high enough
        $this->autoAssignBlueprint($scan);

        // Clean up preprocessed image
        if (file_exists($preprocessedPath) && $preprocessedPath !== $fullPath) {
            unlink($preprocessedPath);
        }
    }

    /**
     * Preprocess image to improve OCR accuracy
     */
    private function preprocessImage(string $imagePath): string
    {
        try {
            $config = config('ocr.preprocessing');
            $image = $this->imageManager->read($imagePath);

            // Resize to optimal size for OCR (not too big, not too small)
            $targetWidth = $config['target_width'];
            if ($image->width() > $targetWidth) {
                $image->scale(width: $targetWidth);
            } elseif ($image->width() < $config['min_width_for_scale_up']) {
                $image->scale(width: $config['scale_up_target']);
            }

            // Convert to grayscale for better OCR
            $image->greyscale();

            // Enhance contrast significantly for text recognition
            $image->brightness($config['brightness']);
            $image->contrast($config['contrast']);

            // Sharpen the image to improve text clarity
            $image->sharpen($config['sharpen']);

            // Save preprocessed image with maximum quality
            $preprocessedPath = sys_get_temp_dir() . '/' . uniqid($config['temp_prefix']) . '.' . $config['format'];
            $image->save($preprocessedPath, quality: $config['quality']);

            Log::info('Image preprocessed', [
                'original_size' => $imagePath,
                'processed_size' => $image->width() . 'x' . $image->height(),
                'processed_path' => $preprocessedPath
            ]);

            return $preprocessedPath;
        } catch (\Exception $e) {
            Log::warning('Image preprocessing failed, using original', ['error' => $e->getMessage()]);
            return $imagePath;
        }
    }

    /**
     * Perform OCR using Tesseract
     */
    private function performOcr(string $imagePath): array
    {
        try {
            $tesseractConfig = config('ocr.tesseract');
            $psmModes = $tesseractConfig['psm_modes'];
            $language = $tesseractConfig['language'];
            $results = [];

            // Method 1: General OCR with sparse text mode
            try {
                $ocr1 = new TesseractOCR($imagePath);
                $ocr1->lang($language);
                $ocr1->psm($psmModes[0]); // First PSM mode (11 - Sparse text)
                $generalText = $ocr1->run();
                $results['general'] = trim($generalText);
            } catch (\Exception $e) {
                Log::warning('OCR Method 1 failed', ['error' => $e->getMessage()]);
                $results['general'] = '';
            }

            // Method 2: Basic OCR without restrictions
            try {
                $ocr2 = new TesseractOCR($imagePath);
                $ocr2->lang($language);
                $ocr2->psm($psmModes[1]); // Second PSM mode (3 - Default segmentation)
                $basicText = $ocr2->run();
                $results['basic'] = trim($basicText);
            } catch (\Exception $e) {
                Log::warning('OCR Method 2 failed', ['error' => $e->getMessage()]);
                $results['basic'] = '';
            }

            // Method 3: Line-by-line OCR
            try {
                $ocr3 = new TesseractOCR($imagePath);
                $ocr3->lang($language);
                $ocr3->psm($psmModes[2]); // Third PSM mode (7 - Single text line)
                $lineText = $ocr3->run();
                $results['lines'] = trim($lineText);
            } catch (\Exception $e) {
                Log::warning('OCR Method 3 failed', ['error' => $e->getMessage()]);
                $results['lines'] = '';
            }

            // Combine all non-empty results
            $allText = array_filter($results);
            $combinedText = implode("\n", $allText);
            $cleanedText = $this->cleanOcrText($combinedText);

            if (config('ocr.debug.log_ocr_results')) {
                Log::info('OCR multiple attempts', [
                    'results' => $results,
                    'combined' => $cleanedText,
                    'found_codes' => $this->findExpansionCodes($cleanedText)
                ]);
            }

            return [
                'text' => $cleanedText,
                'confidence' => $this->calculateOcrConfidence($cleanedText),
                'timestamp' => now()->toISOString(),
                'raw_results' => $results
            ];
        } catch (\Exception $e) {
            Log::error('All OCR methods failed', ['error' => $e->getMessage()]);

            return [
                'text' => '',
                'confidence' => 0,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Clean and normalize OCR text
     */
    private function cleanOcrText(string $text): string
    {
        // Remove duplicates and clean up
        $lines = explode("\n", $text);
        $cleaned = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !in_array($line, $cleaned)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }

    /**
     * Extract card information from OCR text
     */
    private function extractCardInformation(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $detectedCards = [];

        // Search for card names using Elasticsearch
        $searchResults = $this->searchCardsInDatabase($text);

        foreach ($searchResults as $result) {
            $detectedCards[] = [
                'blueprint_id' => $result['blueprint_id'],
                'name' => $result['name'],
                'expansion' => $result['expansion'] ?? null,
                'confidence' => $result['confidence'],
                'matched_text' => $result['matched_text'],
                'image_url' => $result['image_url'] ?? null,
                'card_number' => $result['card_number'] ?? null,
            ];
        }

        return $detectedCards;
    }

    /**
     * Search for cards in database using extracted text
     */
    private function searchCardsInDatabase(string $text): array
    {
        try {
            Log::info('OCR extracted text for card search', ['text' => $text]);

            $results = [];
            $lines = explode("\n", $text);
            $cleanText = strtolower(trim($text));

            // 1. Search for expansion codes (highest priority)
            $expansionMatches = $this->findExpansionCodes($text);
            if (!empty($expansionMatches)) {
                foreach ($expansionMatches as $code) {
                    $cards = $this->searchByExpansionCode($code);
                    $results = array_merge($results, $cards);
                }
            }

            // 2. Search for card names
            foreach ($lines as $line) {
                $line = trim($line);
                if (strlen($line) < 3) continue;

                // Search for exact card names in database
                $cards = $this->searchByCardName($line);
                $results = array_merge($results, $cards);
            }

            // Remove duplicates and sort by confidence
            $results = $this->deduplicateAndSort($results);

            if (config('ocr.debug.log_search_results')) {
                Log::info('Card search results', [
                    'count' => count($results),
                    'codes_found' => $expansionMatches,
                    'sample_results' => array_slice($results, 0, config('ocr.debug.sample_results_count'))
                ]);
            }

            return array_slice($results, 0, config('ocr.search.max_results')); // Return top N matches
        } catch (\Exception $e) {
            Log::error('Card search failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Find expansion codes in OCR text (e.g., OP01-001, ST01-001)
     */
    private function findExpansionCodes(string $text): array
    {
        $codes = [];
        $patterns = config('ocr.expansion_patterns');
        $defaultPrefix = config('ocr.search.default_expansion_prefix');

        // Clean text for better pattern matching
        $cleanText = preg_replace('/[^\w\-\.\s]/u', ' ', $text);

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $cleanText, $matches)) {
                foreach ($matches[1] as $match) {
                    // Normalizza il codice rimuovendo spazi e sostituendo punti
                    $normalizedCode = strtoupper(preg_replace('/\s+/', '', $match));
                    $normalizedCode = str_replace('.', '-', $normalizedCode);

                    // Se è solo numeri, prova ad aggiungere il prefisso di default
                    if (preg_match('/^\d{2,3}-\d{2,3}$/', $normalizedCode)) {
                        $codes[] = $defaultPrefix . '-' . substr($normalizedCode, -3);
                    } elseif (preg_match('/^\d{3}$/', $normalizedCode)) {
                        // Per pattern come "100" alla fine del testo
                        $codes[] = $defaultPrefix . '-' . $normalizedCode;
                    } else {
                        $codes[] = $normalizedCode;
                    }
                }
            }
        }

        return array_unique($codes);
    }

    /**
     * Search cards by expansion code
     */
    private function searchByExpansionCode(string $code): array
    {
        $results = [];

        // Cerca carte con il codice esatto
        $blueprints = Blueprint::whereHas('expansion', function ($query) use ($code) {
            $query->where('code', 'like', '%' . substr($code, 0, 4) . '%'); // OP01, ST01, etc.
        })
            ->where('card_number', 'like', '%' . substr($code, -3) . '%') // 001, 100, etc.
            ->with(['expansion'])
            ->limit(config('ocr.search.expansion_code_limit'))
            ->get();

        foreach ($blueprints as $blueprint) {
            $results[] = [
                'blueprint_id' => $blueprint->id,
                'name' => $blueprint->name,
                'expansion' => $blueprint->expansion?->name,
                'confidence' => config('ocr.confidence.expansion_code_match'),
                'matched_text' => $code,
                'image_url' => $this->getProxiedImageUrl($blueprint->image_url),
                'card_number' => $blueprint->card_number,
            ];
        }

        return $results;
    }

    /**
     * Search cards by name
     */
    private function searchByCardName(string $name): array
    {
        $results = [];
        $cleanName = trim($name);
        $minLength = config('ocr.search.min_text_length');
        $threshold = config('ocr.confidence.card_name_threshold');

        if (strlen($cleanName) < $minLength) return [];

        // Cerca per nome esatto o parziale
        $blueprints = Blueprint::where('name', 'like', '%' . $cleanName . '%')
            ->with(['expansion'])
            ->limit(config('ocr.search.card_name_limit'))
            ->get();

        foreach ($blueprints as $blueprint) {
            $confidence = $this->calculateMatchConfidence($cleanName, $blueprint->name);

            if ($confidence > $threshold) {
                $results[] = [
                    'blueprint_id' => $blueprint->id,
                    'name' => $blueprint->name,
                    'expansion' => $blueprint->expansion?->name,
                    'confidence' => $confidence,
                    'matched_text' => $cleanName,
                    'image_url' => $this->getProxiedImageUrl($blueprint->image_url),
                    'card_number' => $blueprint->card_number,
                ];
            }
        }

        return $results;
    }

    /**
     * Remove duplicates and sort by confidence
     */
    private function deduplicateAndSort(array $results): array
    {
        // Rimuovi duplicati basati su blueprint_id
        $unique = [];
        foreach ($results as $result) {
            $id = $result['blueprint_id'];
            if (!isset($unique[$id]) || $unique[$id]['confidence'] < $result['confidence']) {
                $unique[$id] = $result;
            }
        }

        // Ordina per confidenza
        $sorted = array_values($unique);
        usort($sorted, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $sorted;
    }

    /**
     * Get proxied image URL to avoid CORS issues
     */
    private function getProxiedImageUrl(?string $originalUrl): ?string
    {
        if (!$originalUrl) {
            return null;
        }

        // If it's a CardTrader image, use our proxy
        if (str_starts_with($originalUrl, 'https://cardtrader.com/uploads/')) {
            return url('/api/image-proxy?url=' . urlencode($originalUrl));
        }

        // For other images, return as-is
        return $originalUrl;
    }

    /**
     * Calculate match confidence between OCR text and card name
     */
    private function calculateMatchConfidence(string $ocrText, string $cardName): float
    {
        $ocrText = strtolower(trim($ocrText));
        $cardName = strtolower(trim($cardName));

        // Exact match
        if ($ocrText === $cardName) {
            return config('ocr.confidence.exact_match');
        }

        // Contains match
        if (str_contains($cardName, $ocrText) || str_contains($ocrText, $cardName)) {
            return config('ocr.confidence.contains_match');
        }

        // Levenshtein distance based similarity
        $distance = levenshtein($ocrText, $cardName);
        $maxLength = max(strlen($ocrText), strlen($cardName));

        if ($maxLength === 0) return 0.0;

        $similarity = (1 - $distance / $maxLength) * 100;

        return max(0, $similarity);
    }

    /**
     * Calculate OCR confidence based on text characteristics
     */
    private function calculateOcrConfidence(string $text): float
    {
        if (empty($text)) return 0.0;

        $boosters = config('ocr.confidence_boosters');
        $confidence = config('ocr.confidence.base_ocr_confidence');

        // Boost confidence for longer text
        if (strlen($text) > 20) $confidence += $boosters['long_text_20_chars'];
        if (strlen($text) > 50) $confidence += $boosters['long_text_50_chars'];

        // Boost confidence for recognizable patterns
        if (preg_match('/[A-Z][a-z]+/', $text)) $confidence += $boosters['title_case_words'];
        if (preg_match('/\d+/', $text)) $confidence += $boosters['contains_numbers'];

        // Reduce confidence for too many special characters
        $specialChars = preg_match_all('/[^A-Za-z0-9\s]/', $text);
        if ($specialChars > strlen($text) * $boosters['special_chars_threshold']) {
            $confidence -= $boosters['special_chars_penalty'];
        }

        return min(config('ocr.confidence.max_confidence'), max(0.0, $confidence));
    }

    /**
     * Calculate overall confidence from detected cards
     */
    private function calculateOverallConfidence(array $detectedCards): float
    {
        if (empty($detectedCards)) return 0.0;

        $totalConfidence = array_sum(array_column($detectedCards, 'confidence'));
        $avgConfidence = $totalConfidence / count($detectedCards);

        return round($avgConfidence, 2);
    }

    /**
     * Auto-assign blueprint if confidence is high enough
     */
    private function autoAssignBlueprint(CardScan $scan): void
    {
        $bestMatch = $scan->best_match;

        if ($bestMatch && $bestMatch['confidence'] >= config('ocr.confidence.auto_assignment_threshold')) {
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
        if (!$scan->isFailed()) {
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
