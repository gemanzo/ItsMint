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
    private readonly GoogleVisionOcrService $googleVisionService;

    public function __construct(
        ElasticsearchService $elasticsearchService,
        GoogleVisionOcrService $googleVisionService
    ) {
        $this->imageManager = new ImageManager(new Driver());
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
     * Process the stored image for OCR using Google Vision API
     */
    private function processImage(CardScan $scan): void
    {
        $scan->update(['status' => 'processing']);

        $fullPath = Storage::disk('public')->path($scan->file_path);

        try {
            // Check if Google Vision is available
            if (!$this->googleVisionService->isAvailable()) {
                throw new \Exception('Google Vision API is not properly configured');
            }

            // Preprocess image for better OCR (optional for Google Vision)
            $preprocessedPath = $this->preprocessImage($fullPath);

            // Perform OCR using Google Vision
            $ocrResult = $this->googleVisionService->extractTextFromImage($preprocessedPath);

            // Extract card information from Google Vision results
            $detectedCards = $this->extractCardInformationFromVision($ocrResult);

            // Update scan with results
            $scan->update([
                'ocr_text' => $ocrResult['text'],
                'ocr_data' => $ocrResult,
                'detected_cards' => $detectedCards,
                'confidence_score' => $ocrResult['confidence'],
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
                $image->scale(width: 2000);
            }

            // Save optimized image
            $preprocessedPath = sys_get_temp_dir() . '/' . uniqid('gv_ocr_') . '.jpg';
            $image->save($preprocessedPath, quality: 90);

            Log::info('Image preprocessed for Google Vision', [
                'original_path' => $imagePath,
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
     * Extract card information from Google Vision results
     */
    private function extractCardInformationFromVision(array $visionResult): array
    {
        $text = $visionResult['text'];
        $blocks = $visionResult['blocks'] ?? [];

        Log::info('=== OCR DEBUG: Google Vision raw text ===', [
            'full_text' => $text,
            'text_length' => strlen($text),
            'line_count' => substr_count($text, "\n") + 1,
            'blocks_count' => count($blocks)
        ]);

        // Extract card info using the Google Vision service
        $cardInfo = $this->googleVisionService->extractCardInfo($blocks);

        // Extract additional card attributes from full text
        $cardAttributes = $this->extractCardAttributes($text);
        $cardInfo = array_merge($cardInfo, $cardAttributes);

        Log::info('=== OCR DEBUG: Extracted card info ===', [
            'potential_codes' => $cardInfo['potential_codes'],
            'potential_names' => $cardInfo['potential_names'],
            'card_color' => $cardInfo['card_color'] ?? 'not_detected',
            'card_type' => $cardInfo['card_type'] ?? 'not_detected',
            'card_rarity' => $cardInfo['card_rarity'] ?? 'not_detected',
            'power_value' => $cardInfo['power_value'] ?? 'not_detected'
        ]);

        // Additional debug for code patterns
        foreach ($cardInfo['potential_codes'] as $i => $codeData) {
            Log::info("=== OCR DEBUG: Code {$i} analysis ===", [
                'original_text' => $codeData['original'] ?? 'N/A',
                'extracted_code' => $codeData['code'],
                'pattern_used' => $codeData['pattern'] ?? 'unknown',
                'confidence' => $codeData['confidence'],
                'interpretation' => $codeData['interpretation'] ?? 'N/A'
            ]);
        }

        // Search for cards in database using the structured data
        $searchResults = [];

        // Search by potential card codes first (highest priority)
        foreach ($cardInfo['potential_codes'] as $codeData) {
            Log::info('=== OCR DEBUG: Searching by code ===', [
                'code' => $codeData['code'],
                'confidence' => $codeData['confidence']
            ]);

            $results = $this->searchByExpansionCode($codeData['code']);

            Log::info('=== OCR DEBUG: Code search results ===', [
                'code' => $codeData['code'],
                'original' => $codeData['original'] ?? $codeData['code'],
                'search_variants' => 'see_searchByExpansionCode',
                'results_count' => count($results),
                'results' => array_slice($results, 0, 3) // First 3 results
            ]);

            foreach ($results as $result) {
                $result['match_type'] = 'code';
                $result['match_confidence'] = $codeData['confidence'];
                // Add card attributes for filtering
                $result['detected_card_type'] = $cardInfo['card_type'] ?? null;
                $result['detected_rarity'] = $cardInfo['card_rarity'] ?? null;
                $result['detected_power'] = $cardInfo['power_value'] ?? null;
                $searchResults[] = $result;
            }
        }

        // Search by potential card names
        foreach ($cardInfo['potential_names'] as $nameData) {
            Log::info('=== OCR DEBUG: Searching by name ===', [
                'name' => $nameData['name'],
                'confidence' => $nameData['confidence']
            ]);

            $results = $this->searchByCardName($nameData['name']);

            Log::info('=== OCR DEBUG: Name search results ===', [
                'name' => $nameData['name'],
                'results_count' => count($results),
                'results' => array_slice($results, 0, 3) // First 3 results
            ]);

            foreach ($results as $result) {
                $result['match_type'] = 'name';
                $result['match_confidence'] = $nameData['confidence'];
                $searchResults[] = $result;
            }
        }

        // Fallback: general text search if no specific matches
        if (empty($searchResults)) {
            Log::info('=== OCR DEBUG: No specific matches, using fallback search ===');
            $searchResults = $this->searchCardsInDatabase($text);
        }

        // Deduplicate and sort results
        $finalResults = $this->deduplicateAndSort($searchResults);

        Log::info('=== OCR DEBUG: Final results ===', [
            'codes_found' => count($cardInfo['potential_codes']),
            'names_found' => count($cardInfo['potential_names']),
            'total_search_results' => count($searchResults),
            'final_results_count' => count($finalResults),
            'final_results' => $finalResults,
            'overall_confidence' => $visionResult['confidence'],
        ]);

        return $finalResults;
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
            Log::info('=== OCR DEBUG: Starting database search ===', [
                'text' => $text,
                'text_length' => strlen($text)
            ]);

            $results = [];
            $lines = explode("\n", $text);
            $cleanText = strtolower(trim($text));

            Log::info('=== OCR DEBUG: Text processing ===', [
                'lines_count' => count($lines),
                'lines' => $lines,
                'clean_text' => $cleanText
            ]);

            // 1. Search for expansion codes (highest priority)
            $expansionMatches = $this->findExpansionCodes($text);

            Log::info('=== OCR DEBUG: Expansion codes found ===', [
                'codes' => $expansionMatches,
                'codes_count' => count($expansionMatches)
            ]);

            if (!empty($expansionMatches)) {
                foreach ($expansionMatches as $code) {
                    Log::info('=== OCR DEBUG: Searching expansion code ===', ['code' => $code]);
                    $cards = $this->searchByExpansionCode($code);
                    Log::info('=== OCR DEBUG: Expansion code results ===', [
                        'code' => $code,
                        'results_count' => count($cards),
                        'results' => $cards
                    ]);
                    $results = array_merge($results, $cards);
                }
            }

            // 2. Search for card names
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (strlen($line) < 3) {
                    Log::info('=== OCR DEBUG: Skipping short line ===', [
                        'line_index' => $index,
                        'line' => $line,
                        'length' => strlen($line)
                    ]);
                    continue;
                }

                Log::info('=== OCR DEBUG: Searching card name ===', [
                    'line_index' => $index,
                    'line' => $line
                ]);

                // Search for exact card names in database
                $cards = $this->searchByCardName($line);

                Log::info('=== OCR DEBUG: Card name results ===', [
                    'line' => $line,
                    'results_count' => count($cards),
                    'results' => array_slice($cards, 0, 3) // First 3 results
                ]);

                $results = array_merge($results, $cards);
            }

            // Remove duplicates and sort by confidence
            $results = $this->deduplicateAndSort($results);

            Log::info('=== OCR DEBUG: Database search complete ===', [
                'total_results_before_dedup' => count($results),
                'final_results_count' => count($results),
                'codes_found' => $expansionMatches,
                'final_results' => array_slice($results, 0, config('ocr.debug.sample_results_count', 5))
            ]);

            return array_slice($results, 0, config('ocr.search.max_results')); // Return top N matches
        } catch (\Exception $e) {
            Log::error('=== OCR DEBUG: Card search failed ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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

        Log::info('=== OCR DEBUG: Finding expansion codes ===', [
            'original_text' => $text,
            'clean_text' => $cleanText,
            'patterns' => $patterns,
            'default_prefix' => $defaultPrefix
        ]);

        // Specific One Piece card layout handling
        // Pattern to match "OP09-001 L 3" and extract only "OP09-001"
        $onePiecePattern = '/\b(OP\d{2}-\d{3})\s*[LCS]?\s*\d*\b/i';
        if (preg_match_all($onePiecePattern, $cleanText, $onePieceMatches)) {
            Log::info('=== OCR DEBUG: One Piece specific pattern matches ===', [
                'pattern' => $onePiecePattern,
                'matches' => $onePieceMatches[1]
            ]);

            foreach ($onePieceMatches[1] as $match) {
                $normalizedCode = strtoupper(trim($match));
                Log::info('=== OCR DEBUG: One Piece code extracted ===', [
                    'raw_match' => $match,
                    'normalized' => $normalizedCode
                ]);
                $codes[] = $normalizedCode;
            }
        }

        foreach ($patterns as $patternIndex => $pattern) {
            Log::info('=== OCR DEBUG: Testing pattern ===', [
                'pattern_index' => $patternIndex,
                'pattern' => $pattern
            ]);

            if (preg_match_all($pattern, $cleanText, $matches)) {
                Log::info('=== OCR DEBUG: Pattern matches found ===', [
                    'pattern' => $pattern,
                    'matches' => $matches
                ]);

                foreach ($matches[1] as $matchIndex => $match) {
                    Log::info('=== OCR DEBUG: Processing match ===', [
                        'match_index' => $matchIndex,
                        'raw_match' => $match
                    ]);

                    // Skip if this looks like a One Piece code with L/C/S suffix
                    // (already handled by specific pattern above)
                    if (preg_match('/^OP\d{2}-\d{3}[LCS]?\d*$/i', trim($match))) {
                        Log::info('=== OCR DEBUG: Skipping One Piece code (handled by specific pattern) ===', [
                            'match' => $match
                        ]);
                        continue;
                    }

                    // Normalizza il codice rimuovendo spazi e sostituendo punti
                    $normalizedCode = strtoupper(preg_replace('/\s+/', '', $match));
                    $normalizedCode = str_replace('.', '-', $normalizedCode);

                    Log::info('=== OCR DEBUG: Normalized code ===', [
                        'raw_match' => $match,
                        'normalized' => $normalizedCode
                    ]);

                    // Se è solo numeri, prova ad aggiungere il prefisso di default
                    if (preg_match('/^\d{2,3}-\d{2,3}$/', $normalizedCode)) {
                        $finalCode = $defaultPrefix . '-' . substr($normalizedCode, -3);
                        Log::info('=== OCR DEBUG: Applied default prefix (format 1) ===', [
                            'normalized' => $normalizedCode,
                            'final_code' => $finalCode
                        ]);
                        $codes[] = $finalCode;
                    } elseif (preg_match('/^\d{3}$/', $normalizedCode)) {
                        // Per pattern come "100" alla fine del testo
                        $finalCode = $defaultPrefix . '-' . $normalizedCode;
                        Log::info('=== OCR DEBUG: Applied default prefix (format 2) ===', [
                            'normalized' => $normalizedCode,
                            'final_code' => $finalCode
                        ]);
                        $codes[] = $finalCode;
                    } else {
                        Log::info('=== OCR DEBUG: Using normalized code as-is ===', [
                            'final_code' => $normalizedCode
                        ]);
                        $codes[] = $normalizedCode;
                    }
                }
            } else {
                Log::info('=== OCR DEBUG: No matches for pattern ===', [
                    'pattern' => $pattern
                ]);
            }
        }

        $uniqueCodes = array_unique($codes);

        Log::info('=== OCR DEBUG: Final expansion codes ===', [
            'all_codes' => $codes,
            'unique_codes' => $uniqueCodes
        ]);

        return $uniqueCodes;
    }

    /**
     * Search cards by expansion code
     */
    private function searchByExpansionCode(string $code): array
    {
        $results = [];

        // Cerca carte usando il collector_number nelle properties JSON
        // Prova diverse varianti del codice per massimizzare le possibilità di match
        $searchVariants = [
            $code,
            strtoupper($code),
            strtolower($code),
            str_replace('-', '.', $code),
            str_replace('.', '-', $code),
            // Remove spaces and normalize
            preg_replace('/\s+/', '', $code),
            preg_replace('/\s+/', '', strtoupper($code)),
            // Add leading zeros if missing
            preg_replace('/([A-Z]{2,3}\d{2,3})-(\d{1,2})$/', '$1-0$2', $code),
            preg_replace('/([A-Z]{2,3}\d{2,3})-(\d{1,2})$/', '$1-00$2', $code),
        ];

        Log::info('=== OCR DEBUG: Searching expansion code variants ===', [
            'original_code' => $code,
            'search_variants' => array_unique($searchVariants)
        ]);

        $query = Blueprint::query();
        foreach ($searchVariants as $variant) {
            if (!empty($variant)) {
                $query->orWhereRaw("properties->>'fixed_properties' LIKE ?", ['%"collector_number":"' . $variant . '"%']);
            }
        }

        $blueprints = $query->with(['expansion'])
            ->limit(config('ocr.search.expansion_code_limit'))
            ->get();

        Log::info('=== OCR DEBUG: Database query results ===', [
            'original_code' => $code,
            'blueprints_found' => count($blueprints),
            'blueprint_ids' => $blueprints->pluck('id')->toArray()
        ]);

        foreach ($blueprints as $blueprint) {
            $collectorNumber = $this->extractCollectorNumber($blueprint->properties);
            $confidence = config('ocr.confidence.expansion_code_match');

            // Boost confidence for exact matches
            if (strtoupper($collectorNumber) === strtoupper($code)) {
                $confidence = config('ocr.confidence.exact_match');
            }

            $results[] = [
                'blueprint_id' => $blueprint->id,
                'name' => $blueprint->name,
                'expansion' => $blueprint->expansion?->name,
                'confidence' => $confidence,
                'matched_text' => $code,
                'image_url' => $this->getProxiedImageUrl($blueprint->image_url),
                'card_number' => $collectorNumber,
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
                    'card_number' => $this->extractCollectorNumber($blueprint->properties),
                    'properties' => $blueprint->properties,
                ];
            }
        }

        return $results;
    }

    /**
     * Remove duplicates and sort by confidence with improved scoring
     */
    private function deduplicateAndSort(array $results): array
    {
        // Rimuovi duplicati basati su blueprint_id e calcola il miglior score
        $unique = [];
        foreach ($results as $result) {
            $id = $result['blueprint_id'];

            // Calculate enhanced confidence score
            $enhancedScore = $this->calculateEnhancedConfidence($result);
            $result['enhanced_confidence'] = $enhancedScore;

            if (!isset($unique[$id]) || $unique[$id]['enhanced_confidence'] < $enhancedScore) {
                $unique[$id] = $result;
            }
        }

        // Ordina per confidenza migliorata, poi per confidenza originale
        $sorted = array_values($unique);
        usort($sorted, function ($a, $b) {
            // Prima per enhanced confidence
            if ($a['enhanced_confidence'] !== $b['enhanced_confidence']) {
                return $b['enhanced_confidence'] <=> $a['enhanced_confidence'];
            }
            // Poi per confidence originale
            return $b['confidence'] <=> $a['confidence'];
        });

        return $sorted;
    }

    /**
     * Calculate enhanced confidence score based on multiple factors
     */
    private function calculateEnhancedConfidence(array $result): float
    {
        $baseConfidence = $result['confidence'];
        $enhancedScore = $baseConfidence;

        // Boost score for exact code matches
        if (isset($result['match_type']) && $result['match_type'] === 'code') {
            $enhancedScore += 20; // Significant boost for code matches
        }

        // Boost score for exact name matches
        if (isset($result['matched_text']) && isset($result['name'])) {
            if (strtolower($result['matched_text']) === strtolower($result['name'])) {
                $enhancedScore += 15; // Boost for exact name matches
            }
        }

        // Additional boost for One Piece expansion codes
        if (isset($result['card_number']) && preg_match('/^(OP|ST)\d{2}-\d{3}$/', $result['card_number'])) {
            $enhancedScore += 10;
        }

        // NEW: Boost for matching card attributes using properties
        if (isset($result['properties']) && is_array($result['properties'])) {
            $fixedProps = $result['properties']['fixed_properties'] ?? [];

            // Check card type match (e.g., Leader detected matches onepiece_rarity: Leader)
            if (
                isset($result['detected_card_type']) &&
                isset($fixedProps['onepiece_rarity']) &&
                strtolower($result['detected_card_type']) === strtolower($fixedProps['onepiece_rarity'])
            ) {
                $enhancedScore += 25; // High boost for type match
            }

            // Check rarity match (e.g., detected "Alternate Art" matches onepiece_rarity)
            if (
                isset($result['detected_rarity']) &&
                isset($fixedProps['onepiece_rarity']) &&
                strtolower($result['detected_rarity']) === strtolower($fixedProps['onepiece_rarity'])
            ) {
                $enhancedScore += 15; // Medium boost for rarity match
            }

            // Small boost for having power value detected (indicates card structure recognition)
            if (isset($result['detected_power']) && $result['detected_power'] > 0) {
                $enhancedScore += 5;
            }
        }

        // Cap the score at a reasonable maximum
        return min(150.0, $enhancedScore);
    }

    /**
     * Extract collector number from blueprint properties
     */
    private function extractCollectorNumber(?array $properties): ?string
    {
        if (!$properties || !isset($properties['fixed_properties'])) {
            return null;
        }

        $fixedProperties = $properties['fixed_properties'];

        if (is_array($fixedProperties) && isset($fixedProperties['collector_number'])) {
            return $fixedProperties['collector_number'];
        }

        return null;
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

    /**
     * Extract additional card attributes from OCR text
     */
    private function extractCardAttributes(string $text): array
    {
        $attributes = [
            'card_type' => null,
            'card_rarity' => null,
            'power_value' => null,
        ];

        $upperText = strtoupper($text);

        // Extract card type (Leader, Character, Event, Stage)
        if (str_contains($upperText, 'LEADER')) {
            $attributes['card_type'] = 'Leader';
        } elseif (str_contains($upperText, 'CHARACTER')) {
            $attributes['card_type'] = 'Character';
        } elseif (str_contains($upperText, 'EVENT')) {
            $attributes['card_type'] = 'Event';
        } elseif (str_contains($upperText, 'STAGE')) {
            $attributes['card_type'] = 'Stage';
        }

        // Extract power value (like 5000, 4000, etc.)
        if (preg_match('/\b(\d{4,5})\b/', $text, $matches)) {
            $powerValue = (int)$matches[1];
            // Validate it's a reasonable power value (1000-10000)
            if ($powerValue >= 1000 && $powerValue <= 10000) {
                $attributes['power_value'] = $powerValue;
            }
        }

        // Extract rarity hints
        if (str_contains($upperText, 'ALTERNATE ART') || str_contains($upperText, 'ALT ART')) {
            $attributes['card_rarity'] = 'Alternate Art';
        } elseif (str_contains($upperText, 'SECRET RARE') || str_contains($upperText, 'SR')) {
            $attributes['card_rarity'] = 'Secret Rare';
        } elseif (str_contains($upperText, 'RARE') || str_contains($upperText, 'R')) {
            $attributes['card_rarity'] = 'Rare';
        } elseif (str_contains($upperText, 'PROMO')) {
            $attributes['card_rarity'] = 'Promo';
        }

        return $attributes;
    }
}
