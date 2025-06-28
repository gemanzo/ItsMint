<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleVisionOcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class TestGoogleVisionCommand extends Command
{
    protected $signature = 'ocr:test-vision {image_path?}';
    protected $description = 'Test Google Vision API OCR functionality';

    public function handle(): int
    {
        try {
            // Check if Google Vision is configured
            $visionService = app(GoogleVisionOcrService::class);

            if (!$visionService->isAvailable()) {
                $this->error('❌ Google Vision API is not properly configured');
                $this->line('Please check:');
                $this->line('- GOOGLE_VISION_ENABLED is set to true');
                $this->line('- GOOGLE_CLOUD_PROJECT_ID is set');
                $this->line('- GOOGLE_APPLICATION_CREDENTIALS points to valid service account key');
                return 1;
            }

            // Test with provided image or ask for one
            $imagePath = $this->argument('image_path');

            if (!$imagePath) {
                $imagePath = $this->ask('Please provide path to test image:');
            }

            if (!$imagePath || !file_exists($imagePath)) {
                $this->error('❌ Image file not found: ' . ($imagePath ?: 'none provided'));
                return 1;
            }

            // Extract text using Google Vision
            $startTime = microtime(true);
            $result = $visionService->extractTextFromImage($imagePath);
            $endTime = microtime(true);

            $processingTime = round(($endTime - $startTime) * 1000, 2);

            // Display results
            $this->line('');
            $this->line('Processing time: ' . $processingTime . 'ms');
            $this->line('Overall confidence: ' . round($result['confidence'], 2) . '%');
            $this->line('Text blocks found: ' . count($result['blocks']));
            $this->line('');

            if (!empty($result['text'])) {
                $this->line($result['text']);
                $this->line('');
            }

            // Show card information extraction
            if (!empty($result['blocks'])) {
                $cardInfo = $visionService->extractCardInfo($result['blocks']);

                if (!empty($cardInfo['potential_codes'])) {
                    $this->line('Potential Codes:');
                    foreach (array_slice($cardInfo['potential_codes'], 0, 3) as $code) {
                        $this->line('  - ' . $code['code'] . ' (confidence: ' . round($code['confidence'], 1) . '%)');
                    }
                }

                if (!empty($cardInfo['potential_names'])) {
                    $this->line('Potential Names:');
                    foreach (array_slice($cardInfo['potential_names'], 0, 3) as $name) {
                        $this->line('  - ' . $name['name'] . ' (confidence: ' . round($name['confidence'], 1) . '%)');
                    }
                }

                if (!empty($cardInfo['potential_numbers'])) {
                    $this->line('Potential Numbers:');
                    foreach (array_slice($cardInfo['potential_numbers'], 0, 3) as $number) {
                        $this->line('  - ' . $number['number'] . ' (confidence: ' . round($number['confidence'], 1) . '%)');
                    }
                }
            }

            $this->line('');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            Log::error('Google Vision test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
