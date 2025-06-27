<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OneLogCard;
use App\Services\OneLogScrapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

final class MirrorOneLogDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:mirror-onelog 
                            {--series-id= : Mirror specific series ID}
                            {--all : Mirror all discovered series IDs}
                            {--everything : Mirror ALL cards without series filter}
                            {--dry-run : Preview the data without storing}
                            {--force : Force re-sync existing cards}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mirror OneLog TCG data to local database for comprehensive card matching';

    /**
     * Known series IDs from OneLog API exploration.
     */
    private array $knownSeriesIds;

    public function __construct()
    {
        parent::__construct();
        $this->knownSeriesIds = config('onelog.knownSeriesIds');
    }

    /**
     * Execute the console command.
     */
    public function handle(OneLogScrapingService $scrapingService): int
    {
        $this->info('🏴‍☠️ Starting OneLog data mirroring...');

        try {
            if ($seriesId = $this->option('series-id')) {
                return $this->mirrorSeriesId($scrapingService, $seriesId);
            }

            if ($this->option('all')) {
                return $this->mirrorAllSeries($scrapingService);
            }

            if ($this->option('everything')) {
                return $this->mirrorEverything($scrapingService);
            }

            // Default: show menu
            return $this->showMenu($scrapingService);
        } catch (Exception $e) {
            $this->error("❌ Mirroring failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Show interactive menu.
     */
    private function showMenu(OneLogScrapingService $scrapingService): int
    {
        $choice = $this->choice(
            'What would you like to do?',
            [
                'Test with known series (556901 - ST01 parallel)',
                'Mirror all known series',
                'Mirror EVERYTHING (all 3374+ cards without filters)',
                'Show current database stats',
                'Test API connection'
            ],
            0
        );

        switch ($choice) {
            case 'Test with known series (556901 - ST01 parallel)':
                return $this->mirrorSeriesId($scrapingService, '556901');
            case 'Mirror all known series':
                return $this->mirrorAllSeries($scrapingService);
            case 'Mirror EVERYTHING (all 3374+ cards without filters)':
                return $this->mirrorEverything($scrapingService);
            case 'Show current database stats':
                return $this->showStats();
            case 'Test API connection':
                return $this->testConnection($scrapingService);
        }

        return Command::SUCCESS;
    }

    /**
     * Mirror specific series ID.
     */
    private function mirrorSeriesId(OneLogScrapingService $scrapingService, string $seriesId): int
    {
        $this->info("🎯 Mirroring series ID: {$seriesId}");

        try {
            $cards = $this->fetchCardsFromSeries($scrapingService, $seriesId);

            if (empty($cards)) {
                $this->warn("No cards found for series {$seriesId}");
                return Command::SUCCESS;
            }

            $this->info("📊 Found " . count($cards) . " cards");

            if ($this->option('dry-run')) {
                $this->previewCards($cards, $seriesId);
                return Command::SUCCESS;
            }

            $stored = $this->storeCards($cards);
            $this->info("✅ Stored {$stored} cards for series {$seriesId}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to mirror series {$seriesId}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Mirror all known series.
     */
    private function mirrorAllSeries(OneLogScrapingService $scrapingService): int
    {
        $this->info('🔄 Mirroring all known series...');

        $totalStored = 0;
        $progressBar = $this->output->createProgressBar(count($this->knownSeriesIds));

        foreach ($this->knownSeriesIds as $seriesId) {
            try {
                $cards = $this->fetchCardsFromSeries($scrapingService, $seriesId);
                if (!empty($cards)) {
                    $stored = $this->storeCards($cards);
                    $totalStored += $stored;
                }
            } catch (Exception $e) {
                $this->warn("\nFailed to process series {$seriesId}: {$e->getMessage()}");
            }

            $progressBar->advance();
            sleep(1); // Be respectful with requests
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("✅ Mirroring completed! Stored {$totalStored} cards total");

        return Command::SUCCESS;
    }

    /**
     * Mirror EVERYTHING - all cards without series filter.
     */
    private function mirrorEverything(OneLogScrapingService $scrapingService): int
    {
        $this->info('🌍 Mirroring ALL cards without series filter...');
        $this->warn('This will fetch ALL 3374+ cards from OneLog API');

        if (!$this->option('force') && !$this->confirm('Are you sure you want to fetch ALL cards?', false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        try {
            $cards = $this->fetchAllCardsWithoutFilter($scrapingService);

            if (empty($cards)) {
                $this->warn("No cards found");
                return Command::SUCCESS;
            }

            $this->info("📊 Found " . count($cards) . " cards");

            if ($this->option('dry-run')) {
                $this->previewCards($cards, 'ALL');
                return Command::SUCCESS;
            }

            $stored = $this->storeCards($cards);
            $this->info("✅ Stored {$stored} cards total");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to mirror everything: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Fetch cards from a specific series using the scraping service.
     */
    private function fetchCardsFromSeries(OneLogScrapingService $scrapingService, string $seriesId): array
    {
        // Use the scraping service to fetch raw data from API
        $cardData = $this->fetchCardDataFromOneLogApi($scrapingService, $seriesId);

        if (empty($cardData)) {
            $this->warn("No raw card data fetched from API");
            return [];
        }

        $this->info("🔍 Processing " . count($cardData) . " raw cards from API...");

        // Process the raw data for our database format
        $processedCards = [];
        $processingErrors = 0;

        foreach ($cardData as $index => $rawCard) {
            try {
                $processedCard = $this->processCardData($rawCard);
                $processedCards[] = $processedCard;

                if ($this->getOutput()->isVerbose()) {
                    $cardId = $rawCard['id'] ?? 'unknown';
                    $cardName = $rawCard['name'] ?? 'unknown';
                    $this->line("✅ Processed card {$cardId}: {$cardName}");
                }
            } catch (Exception $e) {
                $processingErrors++;
                $cardId = $rawCard['id'] ?? 'unknown';
                $cardName = $rawCard['name'] ?? 'unknown';

                $this->error("❌ Failed to process card {$cardId} ({$cardName}): {$e->getMessage()}");

                Log::warning("Failed to process card from API", [
                    'card_index' => $index,
                    'card_id' => $cardId,
                    'card_name' => $cardName,
                    'error' => $e->getMessage(),
                    'raw_card' => $rawCard
                ]);
            }
        }

        $this->info("📊 Processing Summary:");
        $this->info("  Raw cards from API: " . count($cardData));
        $this->info("  Successfully processed: " . count($processedCards));
        $this->info("  Processing errors: {$processingErrors}");

        if ($processingErrors > 0) {
            $this->warn("⚠️ {$processingErrors} cards failed processing - check logs for details");
        }

        return $processedCards;
    }

    /**
     * Use the scraping service to fetch raw card data from OneLog API.
     */
    private function fetchCardDataFromOneLogApi(OneLogScrapingService $scrapingService, string $seriesId): array
    {
        $allCards = [];
        $page = 0;
        $hasMorePages = true;

        while ($hasMorePages) {
            $pageCards = $this->fetchSinglePageFromApi($scrapingService, $seriesId, $page);

            if (empty($pageCards)) {
                $hasMorePages = false;
            } else {
                $allCards = array_merge($allCards, $pageCards);
                $page++;

                if (!$this->option('dry-run')) {
                    sleep(1); // Be respectful with requests
                }
            }
        }

        return $allCards;
    }

    /**
     * Fetch a single page using the scraping service.
     */
    private function fetchSinglePageFromApi(OneLogScrapingService $scrapingService, string $seriesId, int $page): array
    {
        try {
            return $scrapingService->fetchSinglePageFromApi($seriesId, $page);
        } catch (Exception $e) {
            Log::error("Error calling OneLog API via service", [
                'series_id' => $seriesId,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fetch ALL cards without series filter - like the user's test.
     */
    private function fetchAllCardsWithoutFilter(OneLogScrapingService $scrapingService): array
    {
        $allCards = [];
        $page = 0;
        $hasMorePages = true;

        $this->info("🔍 Fetching ALL cards without series filter...");

        while ($hasMorePages) {
            $this->line("Fetching page {$page}...");

            $pageCards = $this->fetchSinglePageWithoutFilter($scrapingService, $page);

            if (empty($pageCards)) {
                $hasMorePages = false;
                $this->line("No more cards on page {$page}, stopping.");
            } else {
                $this->line("Found " . count($pageCards) . " cards on page {$page}");

                // Process the raw data for our database format
                $processedCards = [];
                foreach ($pageCards as $rawCard) {
                    try {
                        $processedCard = $this->processCardData($rawCard);
                        $processedCards[] = $processedCard;
                    } catch (Exception $e) {
                        $this->warn("Failed to process card: {$e->getMessage()}");
                    }
                }

                $allCards = array_merge($allCards, $processedCards);
                $page++;

                if (!$this->option('dry-run')) {
                    sleep(1); // Be respectful with requests
                }
            }
        }

        $this->info("📊 Total cards fetched: " . count($allCards));
        return $allCards;
    }

    /**
     * Fetch a single page WITHOUT series filter.
     */
    private function fetchSinglePageWithoutFilter(OneLogScrapingService $scrapingService, int $page): array
    {
        try {
            return $scrapingService->fetchSinglePageWithoutFilter($page);
        } catch (Exception $e) {
            Log::error("Error calling OneLog API without filter", [
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Store cards in database.
     */
    private function storeCards(array $cards): int
    {
        $stored = 0;
        $skipped = 0;
        $errors = 0;
        $updated = 0;
        $created = 0;

        $this->info("💾 Starting to store " . count($cards) . " cards...");

        foreach ($cards as $cardData) {
            try {
                if (empty($cardData['onelog_id'])) {
                    $this->warn("⚠️ Skipping card with missing onelog_id");
                    Log::channel('single')->error("Card with missing onelog_id", ['card_data' => $cardData]);
                    $errors++;
                    continue;
                }

                $existing = OneLogCard::where('onelog_id', $cardData['onelog_id'])->first();

                if ($existing) {
                    if ($this->option('force')) {
                        $existing->update($cardData);
                        $updated++;
                        $stored++;

                        if ($this->option('dry-run') || $this->getOutput()->isVerbose()) {
                            $this->line("🔄 Updated: {$cardData['onelog_id']} - {$cardData['name']}");
                        }
                    } else {
                        $skipped++;

                        if ($this->option('dry-run') || $this->getOutput()->isVerbose()) {
                            $this->line("⏭️ Skipped existing: {$cardData['onelog_id']} - {$cardData['name']}");
                        }
                    }
                } else {
                    OneLogCard::create($cardData);
                    $created++;
                    $stored++;

                    if ($this->option('dry-run') || $this->getOutput()->isVerbose()) {
                        $this->line("✨ Created: {$cardData['onelog_id']} - {$cardData['name']}");
                    }
                }
            } catch (Exception $e) {
                $errors++;
                $cardId = $cardData['onelog_id'] ?? 'unknown';
                $cardName = $cardData['name'] ?? 'unknown';

                $this->error("❌ Failed to store card {$cardId} ({$cardName}): {$e->getMessage()}");

                // Force log to single channel to ensure visibility
                Log::channel('single')->error("FAILED TO STORE ONELOG CARD", [
                    'card_id' => $cardId,
                    'card_name' => $cardName,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'card_data' => $cardData,
                    'timestamp' => now()->toISOString()
                ]);

                // Also write directly to Laravel log
                error_log("ONELOG CARD ERROR [{$cardId}]: " . $e->getMessage());

                // Also log to console for immediate visibility
                if ($this->getOutput()->isVerbose()) {
                    $this->line("Full error details: " . $e->getTraceAsString());
                }
            }
        }

        // Summary report
        $this->newLine();
        $this->info("📊 Storage Summary:");
        $this->info("  Total processed: " . count($cards));
        $this->info("  Successfully stored: {$stored}");
        $this->info("    - Created: {$created}");
        $this->info("    - Updated: {$updated}");
        $this->info("  Skipped (existing): {$skipped}");
        $this->info("  Errors: {$errors}");

        if ($errors > 0) {
            $this->warn("⚠️ {$errors} cards had errors - check storage/logs/laravel.log for details");
            Log::channel('single')->error("Storage completed with errors", [
                'total_errors' => $errors,
                'total_processed' => count($cards),
                'success_rate' => round((($stored / count($cards)) * 100), 2) . '%'
            ]);
        }

        if ($skipped > 0 && !$this->option('force')) {
            $this->info("💡 Use --force to update existing cards");
        }

        return $stored;
    }

    /**
     * Process raw card data.
     */
    private function processCardData(array $rawCard): array
    {
        // Validate required fields
        if (!isset($rawCard['id']) || empty($rawCard['id'])) {
            throw new Exception("Missing required field 'id'");
        }

        if (!isset($rawCard['name']) || empty($rawCard['name'])) {
            Log::warning("Card missing name", ['card_id' => $rawCard['id']]);
        }

        if (!isset($rawCard['serial']) || empty($rawCard['serial'])) {
            Log::warning("Card missing serial", [
                'card_id' => $rawCard['id'],
                'card_name' => $rawCard['name'] ?? 'unknown'
            ]);
        }

        $processedCard = [
            'onelog_id' => (string)$rawCard['id'],
            'img_id' => $rawCard['img_id'] ?? null,
            'series_id' => $rawCard['series_id'] ?? null,
            'name' => $rawCard['name'] ?? null,
            'serial' => $rawCard['serial'] ?? null,
            'rarity' => $rawCard['rarity'] ?? null,
            'type' => $rawCard['type'] ?? null,
            'cost' => is_numeric($rawCard['cost'] ?? null) ? (int)$rawCard['cost'] : null,
            'attribute' => $rawCard['attribute'] ?? null,
            'power' => is_numeric($rawCard['power'] ?? null) ? (int)$rawCard['power'] : null,
            'counter' => ($rawCard['counter'] ?? null) === '-' ? null : $rawCard['counter'],
            'colors' => $this->processColors($rawCard['color'] ?? ''),
            'feature' => $rawCard['feature'] ?? null,
            'effect' => $rawCard['effect'] ?? null,
            'card_sets' => $rawCard['card_sets'] ?? null,
            'trigger' => $rawCard['trigger'] ?? null,
            'remarks' => $this->processRemarks($rawCard['remarks'] ?? null),
            'base_serial' => $this->extractBaseSerial($rawCard['serial'] ?? ''),
            'expansion_code' => $this->extractExpansionCode($rawCard['serial'] ?? ''),
            'is_variant' => $this->isVariant($rawCard),
            'is_promo' => $this->isPromo($rawCard),
        ];

        // Log problematic cards for debugging
        if ($this->getOutput()->isDebug()) {
            $issues = [];

            if (empty($processedCard['name'])) $issues[] = 'missing_name';
            if (empty($processedCard['serial'])) $issues[] = 'missing_serial';
            if (empty($processedCard['series_id'])) $issues[] = 'missing_series_id';

            if (!empty($issues)) {
                Log::info("Card with potential issues", [
                    'card_id' => $processedCard['onelog_id'],
                    'issues' => $issues,
                    'raw_card' => $rawCard
                ]);
            }
        }

        return $processedCard;
    }

    /**
     * Process colors into array.
     */
    private function processColors(string $colorString): array
    {
        if (empty($colorString)) return [];

        $colorMapping = [
            'Red' => 'Red',
            'red' => 'Red',
            'Blue' => 'Blue',
            'blue' => 'Blue',
            'Green' => 'Green',
            'green' => 'Green',
            'Yellow' => 'Yellow',
            'yellow' => 'Yellow',
            'Black' => 'Black',
            'black' => 'Black',
            'Purple' => 'Purple',
            'purple' => 'Purple',
        ];

        $colors = [];

        if (Str::contains($colorString, '/')) {
            foreach (explode('/', $colorString) as $color) {
                $color = trim($color);
                if (isset($colorMapping[$color])) {
                    $colors[] = $colorMapping[$color];
                }
            }
        } elseif (isset($colorMapping[$colorString])) {
            $colors[] = $colorMapping[$colorString];
        }

        return array_unique($colors);
    }

    /**
     * Extract base serial (remove variant suffixes).
     */
    private function extractBaseSerial(string $serial): string
    {
        return preg_replace('/_p\d+$|_parallel$|_alt$/', '', $serial);
    }

    /**
     * Extract expansion code.
     */
    private function extractExpansionCode(string $serial): ?string
    {
        return preg_match('/^([A-Z]+\d+)/', $serial, $matches) ? $matches[1] : null;
    }

    /**
     * Check if card is variant.
     */
    private function isVariant(array $card): bool
    {
        $imgId = $card['img_id'] ?? '';
        $name = $card['name'] ?? '';

        return Str::contains($imgId, ['_p', '_parallel']) || Str::contains($name, '(Parallel)');
    }

    /**
     * Check if card is promo.
     */
    private function isPromo(array $card): bool
    {
        $cardSets = $card['card_sets'] ?? '';
        return Str::contains($cardSets, ['PREMIUM', 'prize', 'CHAMPIONSHIP', 'ANNIVERSARY']);
    }

    /**
     * Process remarks field - can be string, array, or null.
     */
    private function processRemarks($remarks): ?array
    {
        if (empty($remarks)) {
            return null;
        }

        // If it's already an array, return as is
        if (is_array($remarks)) {
            return $remarks;
        }

        // If it's a string, wrap in array
        if (is_string($remarks)) {
            return [$remarks];
        }

        // For other types, convert to string and wrap in array
        return [(string)$remarks];
    }

    /**
     * Preview cards.
     */
    private function previewCards(array $cards, string $seriesId): void
    {
        $this->info("🔍 Preview for series {$seriesId}:");

        $headers = ['ID', 'Serial', 'Name', 'Type', 'Rarity', 'Colors'];
        $rows = [];

        foreach (array_slice($cards, 0, 10) as $card) {
            $colors = is_array($card['colors']) ? implode(', ', $card['colors']) : 'N/A';

            $rows[] = [
                $card['onelog_id'],
                $card['serial'] ?? 'N/A',
                Str::limit($card['name'] ?? 'N/A', 30),
                $card['type'] ?? 'N/A',
                $card['rarity'] ?? 'N/A',
                $colors,
            ];
        }

        $this->table($headers, $rows);

        if (count($cards) > 10) {
            $this->info('... and ' . (count($cards) - 10) . ' more cards');
        }
    }

    /**
     * Show database stats.
     */
    private function showStats(): int
    {
        $total = OneLogCard::count();
        $variants = OneLogCard::where('is_variant', true)->count();
        $promos = OneLogCard::where('is_promo', true)->count();

        $this->info("📊 OneLog Database Stats:");
        $this->info("  Total cards: {$total}");
        $this->info("  Variants: {$variants}");
        $this->info("  Promos: {$promos}");

        if ($total > 0) {
            $variantRate = round(($variants / $total) * 100, 1);
            $promoRate = round(($promos / $total) * 100, 1);
            $this->info("  Variant rate: {$variantRate}%");
            $this->info("  Promo rate: {$promoRate}%");
        }

        return Command::SUCCESS;
    }

    /**
     * Test API connection using the scraping service.
     */
    private function testConnection(OneLogScrapingService $scrapingService): int
    {
        $this->info('🔍 Testing API connection...');

        try {
            $result = $scrapingService->testApiConnection();

            if ($result['success']) {
                $this->info("✅ API connection successful!");
                $this->info("Cards found: " . $result['cards_found']);

                if (!empty($result['sample_card'])) {
                    $this->info("Sample card: " . ($result['sample_card']['name'] ?? 'N/A'));
                }
            } else {
                $this->error("❌ API connection failed: " . ($result['error'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (Exception $e) {
            $this->error("❌ Connection test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
