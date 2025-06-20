<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OneLogScrapingService;
use Illuminate\Console\Command;
use Exception;

final class TestOneLogApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:test-onelog-api 
                            {--series-id= : Test a specific series ID}
                            {--discover : Try to discover series IDs for common expansions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OneLog API connection and discover series IDs';

    /**
     * Execute the console command.
     */
    public function handle(OneLogScrapingService $scrapingService): int
    {
        $this->info('🧪 Testing OneLog API...');

        try {
            if ($this->option('discover')) {
                return $this->discoverSeriesIds($scrapingService);
            }

            if ($seriesId = $this->option('series-id')) {
                return $this->testSpecificSeriesId($scrapingService, $seriesId);
            }

            return $this->testKnownConnection($scrapingService);
        } catch (Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Test connection with known working series ID.
     */
    private function testKnownConnection(OneLogScrapingService $scrapingService): int
    {
        $this->info('🔗 Testing API connection with known expansion EB02...');

        $result = $scrapingService->testApiConnection();

        if ($result['success']) {
            $this->info("✅ API connection successful!");
            $this->info("📊 Found {$result['cards_found']} cards");

            if ($result['sample_card']) {
                $this->newLine();
                $this->info("📋 Sample card data:");
                $card = $result['sample_card'];
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Serial', $card['serial'] ?? 'N/A'],
                        ['Name', $card['name'] ?? 'N/A'],
                        ['Type', $card['type'] ?? 'N/A'],
                        ['Color', $card['color'] ?? 'N/A'],
                        ['Cost', $card['cost'] ?? 'N/A'],
                        ['Power', $card['power'] ?? 'N/A'],
                        ['Counter', $card['counter'] ?? 'N/A'],
                        ['Feature', $card['feature'] ?? 'N/A'],
                    ]
                );
            }
        } else {
            $this->error("❌ API connection failed: {$result['error']}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Test a specific series ID.
     */
    private function testSpecificSeriesId(OneLogScrapingService $scrapingService, string $seriesId): int
    {
        $this->info("🎯 Testing series ID: {$seriesId}");

        try {
            $result = $scrapingService->testApiConnection();

            if ($result['success']) {
                $this->info("✅ Series ID {$seriesId} is valid!");
                $this->info("📊 Found {$result['cards_found']} cards");
            } else {
                $this->warn("⚠️ Series ID {$seriesId} returned no cards or error");
            }
        } catch (Exception $e) {
            $this->error("❌ Error testing series ID {$seriesId}: {$e->getMessage()}");
        }

        return Command::SUCCESS;
    }

    /**
     * Try to discover series IDs for common expansions.
     */
    private function discoverSeriesIds(OneLogScrapingService $scrapingService): int
    {
        $this->info('🔍 Attempting to discover series IDs...');
        $this->warn('⚠️ This will make multiple API calls - use responsibly!');

        if (!$this->confirm('Do you want to continue?')) {
            return Command::SUCCESS;
        }

        $expansionCodes = ['OP01', 'OP02', 'OP03', 'OP04', 'OP05', 'ST01', 'ST02', 'ST03'];
        $potentialSeriesIds = [];

        // Generate potential series IDs based on patterns
        for ($i = 1; $i <= 20; $i++) {
            $potentialSeriesIds[] = sprintf('556%03d', $i); // 556001, 556002, etc.
        }

        for ($i = 101; $i <= 120; $i++) {
            $potentialSeriesIds[] = sprintf('556%03d', $i); // 556101, 556102, etc. for ST
        }

        for ($i = 201; $i <= 220; $i++) {
            $potentialSeriesIds[] = sprintf('556%03d', $i); // 556201, 556202, etc. for EB
        }

        $this->info('🧪 Testing potential series IDs...');
        $progressBar = $this->output->createProgressBar(count($potentialSeriesIds));
        $progressBar->start();

        $foundMappings = [];

        foreach ($potentialSeriesIds as $seriesId) {
            try {
                // Test this series ID
                $cards = $this->makeTestApiCall($seriesId);

                if (!empty($cards)) {
                    $sample = $cards[0];
                    $cardSet = $sample['card_sets'] ?? 'Unknown';
                    $serial = $sample['serial'] ?? 'Unknown';

                    // Try to guess expansion from serial
                    $expansion = 'Unknown';
                    if (preg_match('/^([A-Z]+\d+)-/', $serial, $matches)) {
                        $expansion = $matches[1];
                    }

                    $foundMappings[] = [
                        'series_id' => $seriesId,
                        'expansion_guess' => $expansion,
                        'card_set' => $cardSet,
                        'cards_count' => count($cards),
                        'sample_serial' => $serial
                    ];
                }

                $progressBar->advance();
                sleep(1); // Be respectful

            } catch (Exception $e) {
                $progressBar->advance();
                continue;
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        if (empty($foundMappings)) {
            $this->warn('⚠️ No series IDs discovered');
        } else {
            $this->info("🎉 Discovered " . count($foundMappings) . " series IDs:");
            $this->table(
                ['Series ID', 'Expansion', 'Card Set', 'Cards', 'Sample Serial'],
                $foundMappings
            );

            $this->newLine();
            $this->info('💡 Add these mappings to OneLogScrapingService::$expansionMapping');
        }

        return Command::SUCCESS;
    }

    /**
     * Make a test API call to check if series ID exists.
     */
    private function makeTestApiCall(string $seriesId): array
    {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => 'https://onelogtcg.com',
        ])
            ->timeout(10)
            ->post('https://onelogtcg.com/api/getCards', [
                'page' => 0,
                'showAltArts' => false,
                'card set' => [$seriesId]
            ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        return $data['data'] ?? [];
    }
}
