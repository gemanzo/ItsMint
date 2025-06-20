<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OneLogScrapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Exception;

final class ScrapeOneLogDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:onelog-scrape 
                            {expansion? : The expansion code to scrape (e.g., OP01, ST01). If not specified, scrapes all expansions}
                            {--dry-run : Preview the data without updating the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape One Piece TCG card data from OneLog and update blueprints. Scrapes all expansions by default (suitable for scheduling)';

    /**
     * Execute the console command.
     */
    public function handle(OneLogScrapingService $scrapingService): int
    {
        $this->info('🏴‍☠️ Starting OneLog scraping...');

        try {
            $expansionCode = $this->argument('expansion');

            if ($expansionCode) {
                // Scrape specific expansion
                return $this->scrapeExpansion($scrapingService, $expansionCode);
            }

            // Default: scrape all expansions (non-interactive, suitable for scheduling)
            return $this->scrapeAllExpansions($scrapingService);
        } catch (Exception $e) {
            $this->error("❌ Scraping failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Scrape all available expansions.
     */
    private function scrapeAllExpansions(OneLogScrapingService $scrapingService): int
    {
        $this->info('🔍 Fetching available expansions...');

        $expansions = $scrapingService->getAvailableExpansions();

        if (empty($expansions)) {
            $this->warn('⚠️ No expansions found');
            return Command::SUCCESS;
        }

        $this->info('📦 Found ' . count($expansions) . ' expansions');

        $progressBar = $this->output->createProgressBar(count($expansions));
        $progressBar->start();

        $totalUpdated = 0;

        foreach ($expansions as $expansion) {
            $expansionCode = $expansion['code'] ?? $expansion['id'] ?? null;

            if (!$expansionCode) {
                $progressBar->advance();
                continue;
            }

            try {
                $updated = $this->processSingleExpansion($scrapingService, $expansionCode);
                $totalUpdated += $updated;
            } catch (Exception $e) {
                $this->warn("\n⚠️ Failed to process {$expansionCode}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Scraping completed! Updated {$totalUpdated} blueprints total");

        return Command::SUCCESS;
    }

    /**
     * Scrape a specific expansion.
     */
    private function scrapeExpansion(OneLogScrapingService $scrapingService, string $expansionCode): int
    {
        $this->info("🎯 Scraping expansion: {$expansionCode}");

        $updated = $this->processSingleExpansion($scrapingService, $expansionCode);

        $this->info("✅ Updated {$updated} blueprints for {$expansionCode}");

        return Command::SUCCESS;
    }

    /**
     * Process a single expansion.
     */
    private function processSingleExpansion(OneLogScrapingService $scrapingService, string $expansionCode): int
    {
        $scrapedCards = $scrapingService->scrapeExpansionCards($expansionCode);

        if (empty($scrapedCards)) {
            $this->warn("⚠️ No cards found for {$expansionCode}");
            return 0;
        }

        $this->info("📊 Found " . count($scrapedCards) . " cards for {$expansionCode}");

        if ($this->option('dry-run')) {
            $this->previewScrapedData($scrapedCards, $expansionCode);
            return 0;
        }

        return $scrapingService->updateBlueprintsWithScrapedData($scrapedCards);
    }



    /**
     * Preview scraped data without updating database.
     */
    private function previewScrapedData(array $scrapedCards, string $expansionCode): void
    {
        $this->info("🔍 DRY RUN - Preview of scraped data for {$expansionCode}:");
        $this->newLine();

        $headers = ['Card Number', 'Name', 'Type', 'Colors', 'Cost', 'Power', 'Counter', 'Promo', 'Card Sets'];
        $rows = [];

        foreach (array_slice($scrapedCards, 0, 10) as $card) { // Show first 10 cards
            $rows[] = [
                $card['collector_number'] ?? 'N/A',
                $card['name'] ?? 'N/A',
                $card['card_type'] ?? 'N/A',
                is_array($card['colors']) ? implode(', ', $card['colors']) : ($card['colors'] ?? 'N/A'),
                $card['cost'] ?? 'N/A',
                $card['power'] ?? 'N/A',
                $card['counter'] ?? 'N/A',
                ($card['is_promo'] ?? false) ? '🎁 YES' : 'No',
                Str::limit($card['card_sets'] ?? 'N/A', 30),
            ];
        }

        $this->table($headers, $rows);

        if (count($scrapedCards) > 10) {
            $this->info('... and ' . (count($scrapedCards) - 10) . ' more cards');
        }

        $this->newLine();
        $this->info('💡 Use without --dry-run to actually update the database');

        // Show promo detection info
        $promoCount = collect($scrapedCards)->where('is_promo', true)->count();
        if ($promoCount > 0) {
            $this->info("🎁 Found {$promoCount} promotional cards");
        }
    }
}
