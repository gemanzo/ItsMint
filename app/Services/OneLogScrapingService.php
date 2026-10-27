<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use App\Models\Expansion;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class OneLogScrapingService
{
    private const BASE_URL = 'https://onelogtcg.com';

    private const API_ENDPOINT = 'https://onelogtcg.com/api/getCards';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const REQUEST_DELAY = 1; // Seconds between requests to be respectful

    private array $colorMapping = [
        'Red' => 'Red',
        'Blue' => 'Blue',
        'Green' => 'Green',
        'Yellow' => 'Yellow',
        'Black' => 'Black',
        'Purple' => 'Purple',
        'red' => 'Red',
        'blue' => 'Blue',
        'green' => 'Green',
        'yellow' => 'Yellow',
        'black' => 'Black',
        'purple' => 'Purple',
    ];

    private array $rarityMapping = [
        'L' => 'Leader',
        'C' => 'Common',
        'UC' => 'Uncommon',
        'R' => 'Rare',
        'SR' => 'Super Rare',
        'SP CARD' => 'Special',
        'SEC' => 'Secret',
        'P' => 'Promo',
        // Fallback mappings for consistency
        'Leader' => 'Leader',
        'Common' => 'Common',
        'Uncommon' => 'Uncommon',
        'Rare' => 'Rare',
        'Super Rare' => 'Super Rare',
        'Secret' => 'Secret',
        'Special' => 'Special',
        'Promo' => 'Promo',
    ];

    /**
     * Mapping from expansion codes to OneLog series IDs.
     * Discovered through API testing.
     */
    private array $expansionMapping = [
        // Starter Decks
        'ST01' => '556001', // Straw Hat Crew
        'ST02' => '556002', // Worst Generation
        'ST03' => '556003', // The Seven Warlords Of The Sea
        'ST04' => '556004', // Animal Kingdom Pirates
        'ST05' => '556005', // One Piece Film Edition
        'ST06' => '556006', // The Navy
        'ST07' => '556007', // Big Mom Pirates
        'ST08' => '556008', // Side Monkey.d.luffy
        'ST09' => '556009', // Side Yamato
        'ST10' => '556010', // The Three Captains
        'ST11' => '556011', // Side Uta
        'ST12' => '556012', // Zoro & Sanji
        'ST13' => '556013', // The Three Brothers' Bond
        'ST14' => '556014', // 3d2y

        // Booster Packs
        'OP01' => '556101', // Romance Dawn
        'OP02' => '556102', // Paramount War
        'OP03' => '556103', // Pillars Of Strength
        'OP04' => '556104', // Kingdoms Of Intrigue
        'OP05' => '556105', // Awakening Of The New Era
        'OP06' => '556106', // Wings Of Captain
        'OP07' => '556107', // 500 Years In The Future
        'OP08' => '556108', // Two Legends
        'OP09' => '556109', // The Four Emperors
        'OP10' => '556110', // Royal Blood
        'OP11' => '556111', // A fist of divine speed
        'OP12' => '556112', // The New World

        // Extra Boosters
        'EB01' => '556201', // Memorial Collection
        'EB02' => '556202', // Anime 25th Collection

        // PRB
        'PRB01' => '556301', // PRB 01

        // Promo Cards
        'PROMO' => '556801', // All Promotional Cards

        // Note: Some series IDs from discovery have mixed cards or alternative names
        // We'll focus on the main expansion mappings for now
    ];

    /**
     * Scrape card data for a specific expansion from OneLog API.
     */
    public function scrapeExpansionCards(string $expansionCode): array
    {
        try {
            $seriesId = $this->getSeriesIdForExpansion($expansionCode);
            if (! $seriesId) {
                throw new Exception("No series ID mapping found for expansion: {$expansionCode}");
            }

            $cardData = $this->fetchCardDataFromOneLogApi($seriesId);
            $processedCards = $this->processCardData($cardData, $expansionCode);

            return $processedCards;
        } catch (Exception $e) {
            Log::error("OneLog API scraping failed for {$expansionCode}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update Blueprint models with scraped data.
     */
    public function updateBlueprintsWithScrapedData(array $scrapedCards): int
    {
        $updatedCount = 0;

        foreach ($scrapedCards as $cardData) {
            $blueprint = $this->findBlueprint($cardData);

            if ($blueprint) {
                $this->updateBlueprintWithCardData($blueprint, $cardData);
                $updatedCount++;
            } else {
                Log::warning("Blueprint not found for collector number: {$cardData['collector_number']}");
            }
        }

        return $updatedCount;
    }

    /**
     * Get series ID for expansion code.
     */
    private function getSeriesIdForExpansion(string $expansionCode): ?string
    {
        return $this->expansionMapping[$expansionCode] ?? null;
    }

    /**
     * Fetch card data from OneLog internal API.
     */
    private function fetchCardDataFromOneLogApi(string $seriesId): array
    {
        $allCards = [];
        $page = 0;
        $hasMorePages = true;

        while ($hasMorePages) {
            $pageCards = $this->fetchSinglePageFromApi($seriesId, $page);

            if (empty($pageCards)) {
                $hasMorePages = false;
            } else {
                $allCards = array_merge($allCards, $pageCards);
                $page++;

                // Be respectful with requests
                sleep(self::REQUEST_DELAY);
            }
        }

        return $allCards;
    }

    /**
     * Fetch a single page from OneLog API.
     */
    public function fetchSinglePageFromApi(string $seriesId, int $page): array
    {
        try {
            $payload = [
                'page' => $page,
                'showAltArts' => true,
                'card set' => [$seriesId],
            ];

            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => self::BASE_URL,
            ])
                ->timeout(30)
                ->post(self::API_ENDPOINT, $payload);

            if (! $response->successful()) {
                Log::warning('OneLog API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload,
                ]);

                return [];
            }

            $data = $response->json();

            if (! isset($data['data']) || ! is_array($data['data'])) {
                Log::warning('Unexpected OneLog API response format', [
                    'response' => $data,
                ]);

                return [];
            }

            return $data['data'];
        } catch (Exception $e) {
            Log::error('Error calling OneLog API', [
                'error' => $e->getMessage(),
                'series_id' => $seriesId,
                'page' => $page,
            ]);

            return [];
        }
    }

    /**
     * Fetch a single page from OneLog API WITHOUT series filter.
     * This matches the user's test: POST with {"page": 0, "showAltArts": true}
     */
    public function fetchSinglePageWithoutFilter(int $page): array
    {
        try {
            $payload = [
                'page' => $page,
                'showAltArts' => true,
                // NO 'card set' filter - fetch ALL cards
            ];

            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => self::BASE_URL,
            ])
                ->timeout(30)
                ->post(self::API_ENDPOINT, $payload);

            if (! $response->successful()) {
                Log::warning('OneLog API request failed (no filter)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload,
                ]);

                return [];
            }

            $data = $response->json();

            if (! isset($data['data']) || ! is_array($data['data'])) {
                Log::warning('Unexpected OneLog API response format (no filter)', [
                    'response' => $data,
                ]);

                return [];
            }

            return $data['data'];
        } catch (Exception $e) {
            Log::error('Error calling OneLog API (no filter)', [
                'error' => $e->getMessage(),
                'page' => $page,
            ]);

            return [];
        }
    }

    /**
     * Process raw card data from OneLog API.
     */
    private function processCardData(array $rawData, string $expansionCode): array
    {
        $processedCards = [];

        foreach ($rawData as $card) {
            try {
                $processedCard = $this->processIndividualCardFromApi($card, $expansionCode);
                if ($processedCard) {
                    $processedCards[] = $processedCard;
                }
            } catch (Exception $e) {
                Log::warning('Failed to process card from API', [
                    'card' => $card,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processedCards;
    }

    /**
     * Process individual card data from OneLog API.
     */
    private function processIndividualCardFromApi(array $card, string $expansionCode): ?array
    {
        try {
            // Extract card number from 'serial' field
            $cardNumber = $card['serial'] ?? null;
            if (! $cardNumber) {
                Log::warning('Card missing serial number', ['card' => $card]);

                return null;
            }

            // Extract colors - handle multi-color cards
            $colors = $this->extractColorsFromApi($card);

            // Determine if this is a promo card and handle rarity accordingly
            $isPromo = $this->isPromoCard($card, $expansionCode);
            $rarity = $isPromo ? 'PROMO' : $this->mapRarity($card['rarity'] ?? null);

            // Extract other properties
            return [
                'collector_number' => $cardNumber, // Use collector_number as the key for matching
                'name' => $card['name'] ?? null,
                'colors' => $colors,
                'card_text' => $card['effect'] ?? null,
                'card_type' => $card['type'] ?? null,
                'cost' => $this->extractCostFromApi($card),
                'power' => $this->extractPowerFromApi($card),
                'counter' => $this->extractCounterFromApi($card),
                'attribute' => $card['attribute'] ?? null,
                'card_archetype' => $card['feature'] ?? null, // Map feature to card_archetype
                'trigger' => $card['trigger'] ?? null,
                'rarity' => $rarity,
                'is_promo' => $isPromo,
                'card_sets' => $card['card_sets'] ?? null, // Store for reference
            ];
        } catch (Exception $e) {
            Log::warning('Error processing individual card from API', [
                'error' => $e->getMessage(),
                'card' => $card,
            ]);

            return null;
        }
    }

    /**
     * Extract colors from API card data.
     */
    private function extractColorsFromApi(array $card): array
    {
        $colors = [];

        if (isset($card['color'])) {
            $colorString = $card['color'];

            // Handle multi-color cards (e.g., "Red/Green", "Blue/Purple")
            if (Str::contains($colorString, '/')) {
                $colorParts = explode('/', $colorString);
                foreach ($colorParts as $color) {
                    $trimmedColor = trim($color);
                    if (isset($this->colorMapping[$trimmedColor])) {
                        $colors[] = $this->colorMapping[$trimmedColor];
                    }
                }
            } else {
                if (isset($this->colorMapping[$colorString])) {
                    $colors[] = $this->colorMapping[$colorString];
                }
            }
        }

        return array_unique($colors);
    }

    /**
     * Extract cost from API card data.
     */
    private function extractCostFromApi(array $card): ?int
    {
        $cost = $card['cost'] ?? null;

        if (is_numeric($cost)) {
            return (int) $cost;
        }

        return null;
    }

    /**
     * Extract power from API card data.
     */
    private function extractPowerFromApi(array $card): ?int
    {
        $power = $card['power'] ?? null;

        if (is_numeric($power)) {
            return (int) $power;
        }

        return null;
    }

    /**
     * Extract counter from API card data.
     */
    private function extractCounterFromApi(array $card): ?string
    {
        $counter = $card['counter'] ?? null;

        // OneLog uses "-" for no counter
        if ($counter === '-' || $counter === null) {
            return null;
        }

        return (string) $counter;
    }

    /**
     * Map rarity to standardized format.
     */
    private function mapRarity(?string $rarity): ?string
    {
        if (! $rarity) {
            return null;
        }

        return $this->rarityMapping[strtoupper($rarity)] ?? $this->rarityMapping[$rarity] ?? $rarity;
    }

    /**
     * Determine if a card is a promotional card.
     */
    private function isPromoCard(array $card, string $expansionCode): bool
    {
        // Check if expansion code is PROMO
        if ($expansionCode === 'PROMO') {
            return true;
        }

        // Check if series_id is the promo series (556801)
        if (isset($card['series_id']) && $card['series_id'] === '556801') {
            return true;
        }

        // Check if card_sets indicates promotional content
        $cardSets = $card['card_sets'] ?? '';
        $promoKeywords = [
            'PREMIUM CARD COLLECTION',
            'CHAMPIONSHIP SET',
            'ANNIVERSARY SET',
            'STORAGE BOX SET',
            'Mini-tin Pack Set',
            'OFFICIAL PLAYMAT LIMITED EDITION',
            'Live Action Edition',
        ];

        foreach ($promoKeywords as $keyword) {
            if (Str::contains($cardSets, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find Blueprint by collector number and handle promo cards specially.
     */
    private function findBlueprint(array $cardData): ?Blueprint
    {
        // For promo cards, use enhanced matching logic
        if ($cardData['is_promo'] ?? false) {
            return $this->findPromoBlueprint($cardData);
        }

        // For regular cards, use standard matching
        $query = Blueprint::whereJsonContains(
            'properties->fixed_properties->collector_number',
            $cardData['collector_number']
        );

        if (! empty($cardData['rarity'])) {
            $query->whereJsonContains('properties->fixed_properties->onepiece_rarity', $cardData['rarity']);
        }

        return $query->first();
    }

    /**
     * Enhanced matching logic for promotional cards.
     */
    private function findPromoBlueprint(array $cardData): ?Blueprint
    {
        $collectorNumber = $cardData['collector_number'];
        $cardSets = $cardData['card_sets'] ?? '';
        $cardName = $cardData['name'] ?? '';

        // Try exact match first
        $result = Blueprint::whereJsonContains(
            'properties->fixed_properties->collector_number',
            $collectorNumber
        )->whereJsonContains('properties->fixed_properties->onepiece_rarity', 'PROMO')
            ->first();

        if ($result) {
            return $result;
        }

        // Try with common promo suffixes (p, pr, promo, etc.)
        $promoSuffixes = ['p', 'pr', 'promo', 'alt', 'parallel'];
        foreach ($promoSuffixes as $suffix) {
            $modifiedNumber = $collectorNumber.$suffix;

            $result = Blueprint::whereJsonContains(
                'properties->fixed_properties->collector_number',
                $modifiedNumber
            )->first();

            if ($result) {
                return $result;
            }
        }

        // Try matching by name and partial collector number (for edge cases)
        if (! empty($cardName)) {
            $result = Blueprint::where('name', 'LIKE', "%{$cardName}%")
                ->whereJsonContains('properties->fixed_properties->onepiece_rarity', 'PROMO')
                ->whereRaw("properties->'fixed_properties'->>'collector_number' LIKE ?", ["%{$collectorNumber}%"])
                ->first();

            if ($result) {
                return $result;
            }
        }

        // Try version matching with card_sets
        if (! empty($cardSets)) {
            $result = $this->findBlueprintByVersionAndCardSets($collectorNumber, $cardSets, $cardName);
            if ($result) {
                return $result;
            }
        }

        Log::warning('Promo card not found with any matching strategy', [
            'collector_number' => $collectorNumber,
            'card_sets' => $cardSets,
            'name' => $cardName,
        ]);

        return null;
    }

    /**
     * Try to match blueprint by version field and card_sets.
     */
    private function findBlueprintByVersionAndCardSets(string $collectorNumber, string $cardSets, string $cardName): ?Blueprint
    {
        // Common mappings between API card_sets and DB version
        $versionMappings = [
            'Winner prize' => 'Championship Promo',
            'CHAMPIONSHIP SET' => 'Championship Promo',
            'PREMIUM CARD COLLECTION 25th ANNIVERSARY EDITION' => '25th Anniversary',
            'PREMIUM CARD COLLECTION -ONE PIECE FILM RED-' => 'Film Red Promo',
            'PREMIUM CARD COLLECTION -UTA-' => 'Uta Promo',
            'PREMIUM CARD COLLECTION -Live Action Edition-' => 'Live Action Promo',
            'ANNIVERSARY SET' => 'Anniversary Promo',
            'STORAGE BOX SET' => 'Storage Box Promo',
            'Mini-tin Pack Set' => 'Mini-tin Promo',
        ];

        foreach ($versionMappings as $cardSetsKeyword => $versionValue) {
            if (Str::contains($cardSets, $cardSetsKeyword)) {
                // Try with exact collector number first
                $result = Blueprint::where('version', 'LIKE', "%{$versionValue}%")
                    ->whereJsonContains('properties->fixed_properties->collector_number', $collectorNumber)
                    ->first();

                if ($result) {
                    return $result;
                }

                // Try with promo suffixes
                $promoSuffixes = ['p', 'pr', 'promo'];
                foreach ($promoSuffixes as $suffix) {
                    $modifiedNumber = $collectorNumber.$suffix;

                    $result = Blueprint::where('version', 'LIKE', "%{$versionValue}%")
                        ->whereJsonContains('properties->fixed_properties->collector_number', $modifiedNumber)
                        ->first();

                    if ($result) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Update Blueprint with card data.
     */
    private function updateBlueprintWithCardData(Blueprint $blueprint, array $cardData): void
    {
        $updateData = array_filter([
            'colors' => $cardData['colors'] ?? null,
            'card_effect' => $cardData['card_text'] ?? null,
            'card_type' => $cardData['card_type'] ?? null,
            'card_cost' => $cardData['cost'] ?? null,
            'card_power' => $cardData['power'] ?? null,
            'card_counter' => $cardData['counter'] ?? null,
            'card_attribute' => $cardData['attribute'] ?? null,
            'card_archetype' => $cardData['card_archetype'] ?? null,
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        if (! empty($updateData)) {
            $blueprint->update($updateData);
        }
    }

    /**
     * Get all available expansion codes with their series IDs.
     */
    public function getAvailableExpansions(): array
    {
        $expansions = [];

        foreach ($this->expansionMapping as $code => $seriesId) {
            $expansions[] = [
                'code' => $code,
                'series_id' => $seriesId,
                'name' => $this->getExpansionName($code),
            ];
        }

        return $expansions;
    }

    /**
     * Get expansion name for code.
     */
    private function getExpansionName(string $code): string
    {
        $names = [
            // Extra Boosters
            'EB01' => 'Extra Booster 01',
            'EB02' => 'Anime 25th Collection',

            // Starter Decks
            'ST01' => 'Starter Deck Straw Hat Crew',
            'ST02' => 'Starter Deck Worst Generation',
            'ST03' => 'Starter Deck The Seven Warlords of the Sea',
            'ST04' => 'Starter Deck Animal Kingdom Pirates',
            'ST05' => 'Starter Deck One Piece Film Edition',
            'ST06' => 'Starter Deck The Navy',
            'ST07' => 'Starter Deck Big Mom Pirates',
            'ST08' => 'Starter Deck Side Monkey.d.luffy',
            'ST09' => 'Starter Deck Side Yamato',
            'ST10' => 'Starter Deck The Three Captains',
            'ST11' => 'Starter Deck Side Uta',
            'ST12' => 'Starter Deck Zoro & Sanji',
            'ST13' => 'Starter Deck The Three Brothers\' Bond',
            'ST14' => 'Starter Deck 3d2y',

            // Booster Packs
            'OP01' => 'Romance Dawn',
            'OP02' => 'Paramount War',
            'OP03' => 'Pillars of Strength',
            'OP04' => 'Kingdoms of Intrigue',
            'OP05' => 'Awakening of the New Era',
            'OP06' => 'Wings of the Captain',
            'OP07' => '500 Years in the Future',
            'OP08' => 'Two Legends',
            'OP09' => 'The Four Emperors',
            'OP10' => 'Royal Blood',
            'OP11' => 'A fist of divine speed',
            'OP12' => 'The New World',

            // PRB
            'PRB01' => 'PRB 01',

            // Promo Cards
            'PROMO' => 'Promotional Cards',
        ];

        return $names[$code] ?? $code;
    }

    /**
     * Test connection to OneLog API with a known expansion.
     */
    public function testApiConnection(): array
    {
        try {
            $cards = $this->fetchSinglePageFromApi('556202', 0);

            return [
                'success' => ! empty($cards),
                'cards_found' => count($cards),
                'sample_card' => $cards[0] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
