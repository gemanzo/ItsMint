<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\Vision\V1\Feature\Type;
use App\Exceptions\GoogleVisionOcrException;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;

final class GoogleVisionOcrService
{
    private ImageAnnotatorClient $client;

    private array $config;

    public function __construct()
    {
        $this->config = config('services.google.vision');

        if (! $this->config['enabled']) {
            throw new GoogleVisionOcrException('Google Vision API is not enabled');
        }

        $this->client = new ImageAnnotatorClient([
            'credentials' => $this->config['key_path'],
            'projectId' => $this->config['project_id'],
        ]);
    }

    /**
     * Extract text from image using Google Vision API
     */
    public function extractTextFromImage(string $imagePath): array
    {
        try {
            // Read image file
            $imageContent = Storage::disk('s3-card-scans')->get($imagePath);
            if ($imageContent === false) {
                throw new GoogleVisionOcrException("Failed to read image file: {$imagePath}");
            }

            // Create image object
            $image = new Image;
            $image->setContent($imageContent);

            // Configure features for text detection
            $features = [
                // TEXT_DETECTION for general text
                (new Feature)->setType(Type::TEXT_DETECTION),
                // DOCUMENT_TEXT_DETECTION for better structured text
                (new Feature)->setType(Type::DOCUMENT_TEXT_DETECTION),
            ];

            // Create annotation request
            $requests = [];
            foreach ($features as $feature) {
                $request = new AnnotateImageRequest;
                $request->setImage($image);
                $request->setFeatures([$feature]);
                $requests[] = $request;
            }

            // Batch process requests
            $batchRequest = new BatchAnnotateImagesRequest;
            $batchRequest->setRequests($requests);

            // Make API call
            $response = $this->client->batchAnnotateImages($batchRequest);

            // Process responses
            $result = $this->processVisionResponse($response);

            return $result;
        } catch (Exception $e) {
            Log::error('Google Vision OCR failed', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            throw new GoogleVisionOcrException('Google Vision OCR failed: ' . $e->getMessage(), 0, $e);
        } finally {
            // Clean up client
            $this->client->close();
        }
    }

    /**
     * Process Google Vision API response
     */
    private function processVisionResponse($response): array
    {
        $responses = $response->getResponses();
        if (empty($responses)) {
            throw new GoogleVisionOcrException('Received an empty response from Vision API.');
        }
        $allText = '';
        $blocks = [];
        $overallConfidence = 0;
        $confidenceCount = 0;

        foreach ($responses as $imageResponse) {
            // Check for errors
            if ($imageResponse->hasError()) {
                $error = $imageResponse->getError();
                throw new GoogleVisionOcrException('Vision API error: ' . $error->getMessage());
            }

            // Process text annotations
            $textAnnotations = $imageResponse->getTextAnnotations();
            if ($textAnnotations->count() > 0) {
                // First annotation contains all detected text
                $firstAnnotation = $textAnnotations->offsetGet(0);
                $allText = $firstAnnotation->getDescription();

                // Extract confidence from first annotation
                if ($firstAnnotation->getScore() > 0) {
                    $overallConfidence += $firstAnnotation->getScore() * 100;
                    $confidenceCount++;
                }
            }

            // Process full text annotation for structured data
            $fullTextAnnotation = $imageResponse->getFullTextAnnotation();
            if ($fullTextAnnotation) {
                $pages = $fullTextAnnotation->getPages();
                foreach ($pages as $page) {
                    $pageBlocks = $page->getBlocks();
                    foreach ($pageBlocks as $block) {
                        $blockText = '';
                        $blockConfidence = 0;
                        $paragraphCount = 0;

                        $paragraphs = $block->getParagraphs();
                        foreach ($paragraphs as $paragraph) {
                            $paragraphText = '';
                            $words = $paragraph->getWords();

                            foreach ($words as $word) {
                                $wordText = '';
                                $symbols = $word->getSymbols();

                                foreach ($symbols as $symbol) {
                                    $wordText .= $symbol->getText();
                                }

                                $paragraphText .= $wordText . ' ';

                                // Accumulate confidence
                                if ($word->getConfidence() > 0) {
                                    $blockConfidence += $word->getConfidence();
                                    $paragraphCount++;
                                }
                            }

                            $blockText .= trim($paragraphText) . "\n";
                        }

                        if (! empty(trim($blockText))) {
                            $blocks[] = [
                                'text' => trim($blockText),
                                'confidence' => $paragraphCount > 0 ? ($blockConfidence / $paragraphCount) * 100 : 0,
                                'bounding_box' => $this->extractBoundingBox($block->getBoundingBox()),
                            ];
                        }
                    }
                }
            }
        }

        $extractedCardInfo = $this->extractCardInfo($blocks);

        return [
            'text' => trim($allText),
            'confidence' => $confidenceCount > 0 ? $overallConfidence / $confidenceCount : 0,
            'blocks' => $blocks,
            'card_info' => $extractedCardInfo,
            'provider' => 'google_vision',
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Extract bounding box coordinates
     */
    private function extractBoundingBox($boundingBox): array
    {
        if (! $boundingBox) {
            return [];
        }

        $vertices = $boundingBox->getVertices();
        $coordinates = [];

        foreach ($vertices as $vertex) {
            $coordinates[] = [
                'x' => $vertex->getX(),
                'y' => $vertex->getY(),
            ];
        }

        return $coordinates;
    }

    /**
     * Clean and normalize extracted text
     */
    public function cleanText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove common OCR artifacts
        $text = preg_replace('/[^\w\s\-\.,\(\)\/\:]/', ' ', $text);

        // Normalize spacing around punctuation
        $text = preg_replace('/\s*([,.])\s*/', '$1 ', $text);

        // Remove multiple spaces
        $text = preg_replace('/\s{2,}/', ' ', $text);

        return trim($text);
    }

    /**
     * Extract potential card information from blocks
     */
    public function extractCardInfo(array $blocks): array
    {
        $cardInfo = [
            'potential_names' => [],
            'potential_codes' => [],
            'potential_numbers' => [],
            'high_confidence_text' => [],
            'card_color' => null,
        ];

        // Extract card color
        $cardInfo['card_color'] = $this->extractCardColor($blocks);

        foreach ($blocks as $block) {
            $text = $block['text'];
            $confidence = $block['confidence'];

            // High confidence text (>80%)
            if ($confidence > 80) {
                $cardInfo['high_confidence_text'][] = [
                    'text' => $text,
                    'confidence' => $confidence,
                ];
            }

            // Look for card codes with improved patterns for One Piece cards
            // Pattern 1: Exact 3-digit codes like OP01-001, ST01-100 (most reliable)
            if (preg_match_all('/\b([A-Z]{2,3}\s?\d{2,3}[\-\.]\s?(\d{3}))\b/', $text, $matches)) {
                foreach ($matches[0] as $index => $match) {
                    // Extract the number part to validate it's exactly 3 digits
                    $numberPart = $matches[2][$index];
                    if (strlen($numberPart) === 3) {
                        $normalizedCode = $this->normalizeCardCode(trim($match));
                        $cardInfo['potential_codes'][] = [
                            'code' => $normalizedCode,
                            'confidence' => $confidence,
                            'original' => trim($match),
                            'pattern' => 'exact_3_digit',
                        ];
                    }
                }
            }

            // Pattern 2: Codes with leading zeros that might be misread (like OP09-0013 -> OP09-001)
            // This catches patterns like "OP 09-0013" where the "13" might actually be "001"
            if (preg_match_all('/\b([A-Z]{2,3}\s?\d{2,3}[\-\.]\s?0+(\d{1,3}))\b/', $text, $matches)) {
                foreach ($matches[0] as $index => $match) {
                    $fullMatch = trim($match);
                    $numberPart = $matches[2][$index];

                    // Special case: if we detect "OP09-0013" or similar, interpret "13" as "001"
                    if (strlen($numberPart) <= 2) {
                        // Create version with number padded to 3 digits with leading zeros
                        // For "OP 09-0013", extract "OP 09" and add "001"
                        $prefixPart = preg_replace('/([A-Z]{2,3}\s?\d{2,3}[\-\.]\s?)0+\d+/', '$1', $fullMatch);
                        $interpretedAs001 = $prefixPart . str_pad($numberPart, 3, '0', STR_PAD_LEFT);
                        $normalizedAs001 = $this->normalizeCardCode($interpretedAs001);

                        $cardInfo['potential_codes'][] = [
                            'code' => $normalizedAs001,
                            'confidence' => $confidence * 1.2, // High confidence for this interpretation
                            'original' => $fullMatch,
                            'pattern' => 'leading_zero_correction',
                            'interpretation' => "Interpreted '{$numberPart}' as '00{$numberPart}' in context '{$fullMatch}'",
                        ];
                    }

                    // Also add the literal interpretation (lower confidence)
                    $literalCode = preg_replace('/([A-Z]{2,3}\s?\d{2,3}[\-\.]\s?)0*(\d+)/', '$1' . str_pad($numberPart, 3, '0', STR_PAD_LEFT), $fullMatch);
                    $normalizedLiteral = $this->normalizeCardCode($literalCode);

                    // Only add if different from the corrected version
                    if (! isset($normalizedAs001) || $normalizedLiteral !== $normalizedAs001) {
                        $cardInfo['potential_codes'][] = [
                            'code' => $normalizedLiteral,
                            'confidence' => $confidence * 0.6,
                            'original' => $fullMatch,
                            'pattern' => 'literal_with_zeros',
                        ];
                    }
                }
            }

            // Pattern 3: Fallback for partial codes like "OP 09"
            if (preg_match_all('/\b([A-Z]{2,3}\s?\d{2,3})\b/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    // Only add if not already captured by previous patterns
                    $found = false;
                    foreach ($cardInfo['potential_codes'] as $existing) {
                        if (str_contains($existing['original'], trim($match))) {
                            $found = true;
                            break;
                        }
                    }

                    if (! $found) {
                        $normalizedCode = $this->normalizeCardCode(trim($match));
                        $cardInfo['potential_codes'][] = [
                            'code' => $normalizedCode,
                            'confidence' => $confidence * 0.6, // Lower confidence for partial codes
                            'original' => trim($match),
                            'pattern' => 'partial_code',
                        ];
                    }
                }
            }

            // Look for card numbers (e.g., "123/456")
            if (preg_match_all('/\b\d{1,4}\/\d{1,4}\b/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $cardInfo['potential_numbers'][] = [
                        'number' => trim($match),
                        'confidence' => $confidence,
                    ];
                }
            }

            // Look for potential card names (capitalized words)
            if (preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    if (strlen($match) > 3) { // Ignore short words
                        $cardInfo['potential_names'][] = [
                            'name' => trim($match),
                            'confidence' => $confidence,
                        ];
                    }
                }
            }
        }

        // Sort by confidence
        foreach ($cardInfo as $key => $items) {
            if (is_array($items) && ! empty($items)) {
                usort($cardInfo[$key], function ($a, $b) {
                    return $b['confidence'] <=> $a['confidence'];
                });
            }
        }

        return $cardInfo;
    }

    /**
     * Normalize card codes for consistent matching
     */
    private function normalizeCardCode(string $code): string
    {
        // Remove extra spaces
        $code = preg_replace('/\s+/', '', $code);

        // Ensure dash separator
        $code = str_replace('.', '-', $code);

        // Remove leading zeros from card number but keep minimum 3 digits
        if (preg_match('/([A-Z]{2,3}\d{2,3})-0*(\d+)/', $code, $matches)) {
            $prefix = $matches[1];
            $number = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
            $code = $prefix . '-' . $number;
        }

        return strtoupper($code);
    }

    /**
     * Extract card color from text and position analysis
     */
    private function extractCardColor(array $blocks): ?string
    {
        $colorKeywords = [
            'red' => ['red', 'rouge', 'rosso', '赤'],
            'blue' => ['blue', 'bleu', 'blu', '青'],
            'green' => ['green', 'vert', 'verde', '緑'],
            'yellow' => ['yellow', 'jaune', 'giallo', '黄'],
            'purple' => ['purple', 'violet', 'viola', '紫'],
            'black' => ['black', 'noir', 'nero', '黒'],
        ];

        foreach ($blocks as $block) {
            $text = strtolower($block['text']);
            foreach ($colorKeywords as $color => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($text, $keyword)) {
                        return $color;
                    }
                }
            }
        }

        // TODO: Implement visual color detection from bounding boxes
        // For now, try to infer from card position or other context clues

        return null;
    }

    /**
     * Check if Google Vision API is available
     */
    public function isAvailable(): bool
    {
        return $this->config['enabled'] &&
            ! empty($this->config['project_id']) &&
            ! empty($this->config['key_path']) &&
            file_exists($this->config['key_path']);
    }
}
