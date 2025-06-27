<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\OneLogCard;
use App\Services\BlueprintClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MatchBlueprintsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:match-blueprints-onelog
                            {--dry-run : Preview matches without storing}
                            {--force : Re-process existing matches (detach and re-attach)}
                            {--resync : Update existing matches without detaching}
                            {--expansion= : Match specific expansion only}
                            {--show-stats : Show matching statistics}
                            {--show-excluded : Show products excluded from matching}
                            {--detailed : Show detailed matching information}
                            {--debug-serial= : Debug specific serial matching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Match ItsMint blueprints with OneLog cards using various matching strategies';

    /**
     * Execute the console command.
     */
    public function handle(BlueprintClassificationService $classificationService): int
    {
        $this->info('🔗 Starting blueprint matching...');

        if ($this->option('show-stats')) {
            return $this->showMatchingStats();
        }

        if ($this->option('show-excluded')) {
            return $this->showExcludedProducts($classificationService);
        }

        if ($debugSerial = $this->option('debug-serial')) {
            return $this->debugSerialMatching($debugSerial);
        }

        try {
            $totalMatches = 0;

            // Get blueprints to process
            $blueprints = $this->getBlueprintsToProcess($classificationService);

            if ($blueprints->isEmpty()) {
                $this->info('No blueprints to process');
                return Command::SUCCESS;
            }

            $this->info("Processing {$blueprints->count()} blueprints...");

            $progressBar = $this->output->createProgressBar($blueprints->count());

            foreach ($blueprints as $blueprint) {
                $matches = $this->findMatches($blueprint);

                if (!empty($matches)) {
                    if ($this->option('detailed')) {
                        $this->newLine();
                        $this->info("Blueprint #{$blueprint->id}: {$blueprint->name} (collector: {$blueprint->collector_number})");
                        foreach ($matches as $match) {
                            $confidence = number_format($match['confidence'], 3);
                            $this->line("  → Matched with OneLog #{$match['card']->id}: {$match['card']->name} (confidence: {$confidence}, type: {$match['type']})");
                        }
                    }

                    if (!$this->option('dry-run')) {
                        $this->storeMatches($blueprint, $matches);
                    }
                    $totalMatches += count($matches);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            if ($this->option('dry-run')) {
                $this->info("🔍 DRY RUN: Found {$totalMatches} potential matches");
            } else {
                $mode = $this->option('force') ? 'force re-sync' : ($this->option('resync') ? 'resync' : 'initial matching');
                $this->info("✅ Completed {$mode}: processed {$totalMatches} matches");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Matching failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Get blueprints to process.
     */
    private function getBlueprintsToProcess(BlueprintClassificationService $classificationService)
    {
        $query = Blueprint::query();

        if ($expansion = $this->option('expansion')) {
            $query->whereHas('expansion', function ($q) use ($expansion) {
                $q->where('code', $expansion);
            });
        }

        if (!$this->option('force') && !$this->option('resync')) {
            // Only process blueprints without existing matches
            $query->whereDoesntHave('oneLogCards');
        }

        // Filter for single cards using new product_type column or fallback to classification
        $total = $query->count();

        // Use database filtering if product_type is available
        $query->where(function ($q) {
            $q->where('product_type', 'single_card')
                ->orWhere(function ($fallbackQuery) {
                    // Fallback for blueprints without product_type classification
                    $fallbackQuery->whereNull('product_type')
                        ->whereIn('ct_category_id', [192, 255]); // Single cards and DON!! tokens
                });
        });

        $filtered = $query->get();

        // For any blueprints without product_type, do runtime classification
        $withoutClassification = $filtered->filter(fn($bp) => is_null($bp->product_type));

        if ($withoutClassification->isNotEmpty()) {
            $this->warn("⚠️  Found {$withoutClassification->count()} blueprints without classification. Consider running: php artisan itsmint:classify-blueprints --save");

            // Filter using runtime classification for those without database classification
            $filtered = $filtered->filter(function ($blueprint) use ($classificationService) {
                if ($blueprint->product_type === 'single_card') {
                    return true;
                }

                if (is_null($blueprint->product_type)) {
                    $classification = $classificationService->classifyBlueprint($blueprint);
                    return in_array($classification, ['single_card', 'likely_single_card']);
                }

                return false;
            });
        }

        $this->info("📊 Filtered {$total} → {$filtered->count()} (single cards only)");

        return $filtered;
    }

    /**
     * Find matching OneLog cards for a blueprint.
     */
    private function findMatches(Blueprint $blueprint): array
    {
        $matches = [];

        // Strategy 1: Exact collector number match
        $exactMatches = $this->findExactMatches($blueprint);
        $matches = array_merge($matches, $exactMatches);

        // Strategy 2: Base serial match (variant matching)
        if (empty($matches)) {
            $baseMatches = $this->findBaseSerialMatches($blueprint);
            $matches = array_merge($matches, $baseMatches);
        }

        // Strategy 3: Fuzzy name matching
        if (empty($matches)) {
            $nameMatches = $this->findNameMatches($blueprint);
            $matches = array_merge($matches, $nameMatches);
        }

        return $matches;
    }

    /**
     * Find exact collector number matches with improved version matching.
     */
    private function findExactMatches(Blueprint $blueprint): array
    {
        $collectorNumber = $blueprint->collector_number;

        if (!$collectorNumber) {
            return [];
        }

        // Get all OneLog cards with this serial
        $cards = OneLogCard::where('serial', $collectorNumber)->get();

        if ($cards->isEmpty()) {
            return [];
        }

        $matches = [];
        $blueprintRarity = $blueprint->rarity; // Uses getRarityAttribute accessor

        foreach ($cards as $card) {
            $matchScore = $this->calculateVersionMatchScore($blueprint, $card, $blueprintRarity);

            if ($matchScore > 0) {
                $matches[] = [
                    'card' => $card,
                    'type' => 'exact_serial',
                    'confidence' => $matchScore,
                    'metadata' => [
                        'match_field' => 'serial',
                        'blueprint_rarity' => $blueprintRarity,
                        'card_rarity' => $card->rarity,
                        'blueprint_is_promo' => $blueprint->is_promo_card,
                        'card_is_promo' => $card->is_promo,
                        'card_is_variant' => $card->is_variant,
                        'blueprint_version' => $blueprint->version,
                        'card_sets' => $card->card_sets,
                        'score_breakdown' => $this->getScoreBreakdown($blueprint, $card, $blueprintRarity)
                    ]
                ];
            }
        }

        // Sort by confidence (highest first) and return only the best matches
        usort($matches, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        // Return top match(es) - if multiple have the same high score, return all of them
        if (!empty($matches)) {
            $topScore = $matches[0]['confidence'];
            return array_filter($matches, fn($match) => $match['confidence'] >= $topScore - 0.05); // Allow small variance
        }

        return [];
    }

    /**
     * Calculate match score between blueprint and OneLog card considering variants, promos, and rarity.
     */
    private function calculateVersionMatchScore(Blueprint $blueprint, OneLogCard $card, ?string $blueprintRarity): float
    {
        $score = 0.0;

        // Base score for serial match
        $score += 0.4;

        // Rarity matching (critical for distinguishing versions)
        $rarityScore = $this->calculateRarityMatchScore($blueprintRarity, $card->rarity);
        $score += $rarityScore * 0.3;

        // Promo status matching
        $isPromoMatch = $blueprint->is_promo_card === $card->is_promo;
        if ($isPromoMatch) {
            $score += 0.2;
        } else {
            // Penalize promo mismatch more heavily
            $score -= 0.15;
        }

        // Version/variant logic
        $variantScore = $this->calculateVariantMatchScore($blueprint, $card);
        $score += $variantScore * 0.05;

        // Version/card_sets matching (importante per set speciali)
        $versionSetsScore = $this->calculateVersionSetsMatchScore($blueprint, $card);
        $score += $versionSetsScore * 0.15;

        // Name matching (fallback verification)
        $nameScore = $this->calculateNameSimilarity(
            $this->normalizeName($blueprint->name),
            $this->normalizeName($card->name)
        );
        $score += $nameScore * 0.05;

        // Ensure score is between 0 and 1
        return max(0.0, min(1.0, $score));
    }

    /**
     * Calculate rarity match score with mapping knowledge.
     */
    private function calculateRarityMatchScore(?string $blueprintRarity, string $cardRarity): float
    {
        if (!$blueprintRarity) {
            return 0.5; // Neutral if unknown
        }

        // Exact rarity mapping
        $rarityMappings = [
            'Common' => ['C'],
            'Uncommon' => ['UC'],
            'Rare' => ['R'],
            'Super Rare' => ['SR'],
            'Secret Rare' => ['SEC'],
            'Leader' => ['L'],
            'Promo' => ['P', 'SR', 'C', 'R', 'L', 'SEC', 'UC'], // Promos can have various rarities
            'DON!!' => ['SR', 'L', 'R'], // DON tokens usually map to these
            'Alternate Art' => ['SR', 'C', 'R', 'L', 'P', 'SEC', 'UC', 'SP CARD'], // Alt arts can be any rarity
            'Special' => ['L', 'SR', 'R', 'SP CARD', 'SEC', 'C'] // Special cards
        ];

        $expectedRarities = $rarityMappings[$blueprintRarity] ?? [];

        if (in_array($cardRarity, $expectedRarities)) {
            // Perfect match gets full score
            if (($blueprintRarity === 'Common' && $cardRarity === 'C') ||
                ($blueprintRarity === 'Uncommon' && $cardRarity === 'UC') ||
                ($blueprintRarity === 'Rare' && $cardRarity === 'R') ||
                ($blueprintRarity === 'Super Rare' && $cardRarity === 'SR') ||
                ($blueprintRarity === 'Secret Rare' && $cardRarity === 'SEC') ||
                ($blueprintRarity === 'Leader' && $cardRarity === 'L')
            ) {
                return 1.0;
            }

            // Acceptable match for multi-rarity categories
            return 0.8;
        }

        return 0.0;
    }

    /**
     * Calculate variant matching score.
     */
    private function calculateVariantMatchScore(Blueprint $blueprint, OneLogCard $card): float
    {
        $blueprintVersion = trim($blueprint->version ?? '');
        $hasSpecialVersion = !empty($blueprintVersion) && $blueprintVersion !== '';

        // If blueprint has special version info and card is variant/parallel
        if ($hasSpecialVersion && $card->is_variant) {
            return 1.0;
        }

        // If blueprint has no special version and card is not variant (base version)
        if (!$hasSpecialVersion && !$card->is_variant) {
            return 1.0;
        }

        // Partial match for other cases
        return 0.5;
    }

    /**
     * Calculate version/card_sets matching score.
     */
    private function calculateVersionSetsMatchScore(Blueprint $blueprint, OneLogCard $card): float
    {
        $blueprintVersion = trim($blueprint->version ?? '');
        $cardSets = trim($card->card_sets ?? '');

        // Se entrambi sono vuoti
        if (empty($blueprintVersion) && empty($cardSets)) {
            return 0.5; // Neutrale
        }

        // Se uno è vuoto e l'altro no
        if (empty($blueprintVersion) || empty($cardSets)) {
            return 0.0; // Nessun match possibile
        }

        // Normalizza entrambe le stringhe per il confronto
        $normalizedVersion = $this->normalizeVersionString($blueprintVersion);
        $normalizedCardSets = $this->normalizeVersionString($cardSets);

        // Calcola la similarità usando parole chiave comuni
        $versionWords = $this->extractKeywords($normalizedVersion);
        $cardSetsWords = $this->extractKeywords($normalizedCardSets);

        if (empty($versionWords) || empty($cardSetsWords)) {
            return 0.0;
        }

        // Conta le parole che matchano
        $matchingWords = array_intersect($versionWords, $cardSetsWords);
        $totalWords = array_unique(array_merge($versionWords, $cardSetsWords));

        $matchScore = count($matchingWords) / count($totalWords);

        // Bonus per match esatti o quasi esatti
        if ($normalizedVersion === $normalizedCardSets) {
            return 1.0;
        }

        // Bonus per match di parole chiave significative
        $significantWords = ['championship', 'promo', 'battle', 'pack', 'collection', 'tournament', 'anniversary', 'special', 'limited', 'edition'];
        $significantMatches = array_intersect($matchingWords, $significantWords);

        if (!empty($significantMatches)) {
            $matchScore += 0.2; // Bonus per parole chiave importanti
        }

        return min(1.0, $matchScore);
    }

    /**
     * Normalizza stringhe di versione per il confronto.
     */
    private function normalizeVersionString(string $version): string
    {
        $normalized = strtolower($version);

        // Rimuovi caratteri speciali ma mantieni spazi
        $normalized = preg_replace('/[^\w\s]/', ' ', $normalized);

        // Rimuovi numeri di anni specifici (per match più flessibili)
        $normalized = preg_replace('/\b20\d{2}\b/', '', $normalized);

        // Normalizza spazi multipli
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Estrae parole chiave significative da una stringa.
     */
    private function extractKeywords(string $text): array
    {
        $words = explode(' ', $text);

        // Filtra parole troppo corte o comuni
        $stopWords = ['the', 'and', 'or', 'vol', 'v', 'of', 'in', 'on', 'at', 'to', 'for', 'with', 'by'];

        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords) && !is_numeric($word);
        });

        return array_values($keywords);
    }

    /**
     * Get detailed score breakdown for debugging.
     */
    private function getScoreBreakdown(Blueprint $blueprint, OneLogCard $card, ?string $blueprintRarity): array
    {
        return [
            'serial_match' => 0.4,
            'rarity_score' => $this->calculateRarityMatchScore($blueprintRarity, $card->rarity),
            'promo_match' => $blueprint->is_promo_card === $card->is_promo,
            'variant_score' => $this->calculateVariantMatchScore($blueprint, $card),
            'version_sets_score' => $this->calculateVersionSetsMatchScore($blueprint, $card),
            'name_similarity' => $this->calculateNameSimilarity(
                $this->normalizeName($blueprint->name),
                $this->normalizeName($card->name)
            )
        ];
    }

    /**
     * Find base serial matches (for variants).
     */
    private function findBaseSerialMatches(Blueprint $blueprint): array
    {
        $collectorNumber = $blueprint->collector_number;

        if (!$collectorNumber) {
            return [];
        }

        // Check if blueprint might be a variant
        $baseSerial = $this->extractBaseSerial($collectorNumber);

        $cards = OneLogCard::where('base_serial', $baseSerial)
            ->orWhere('serial', $baseSerial)
            ->get();

        return $cards->map(function ($card) use ($collectorNumber, $baseSerial) {
            $confidence = $card->serial === $collectorNumber ? 1.0 : 0.8;

            return [
                'card' => $card,
                'type' => 'base_serial',
                'confidence' => $confidence,
                'metadata' => [
                    'blueprint_serial' => $collectorNumber,
                    'matched_base_serial' => $baseSerial,
                    'card_serial' => $card->serial
                ]
            ];
        })->toArray();
    }

    /**
     * Find name-based matches.
     */
    private function findNameMatches(Blueprint $blueprint): array
    {
        $blueprintName = $this->normalizeName($blueprint->name);

        if (empty($blueprintName)) {
            return [];
        }

        $matches = [];

        // Try exact name match first
        $exactNameCards = OneLogCard::whereRaw('LOWER(name) = ?', [strtolower($blueprint->name)])->get();

        foreach ($exactNameCards as $card) {
            $matches[] = [
                'card' => $card,
                'type' => 'exact_name',
                'confidence' => 0.9,
                'metadata' => ['matched_name' => $card->name]
            ];
        }

        // If no exact matches, try fuzzy matching
        if (empty($matches)) {
            $fuzzyMatches = $this->findFuzzyNameMatches($blueprint);
            $matches = array_merge($matches, $fuzzyMatches);
        }

        return $matches;
    }

    /**
     * Find fuzzy name matches.
     */
    private function findFuzzyNameMatches(Blueprint $blueprint): array
    {
        $blueprintName = $this->normalizeName($blueprint->name);
        $matches = [];

        // Get potential cards from same expansion
        $expansionCode = $this->extractExpansionCode($blueprint->collector_number ?? '');

        $query = OneLogCard::query();
        if ($expansionCode) {
            $query->where('expansion_code', $expansionCode);
        }

        $cards = $query->get();

        foreach ($cards as $card) {
            $cardName = $this->normalizeName($card->name);
            $similarity = $this->calculateNameSimilarity($blueprintName, $cardName);

            if ($similarity >= 0.7) { // 70% similarity threshold
                $matches[] = [
                    'card' => $card,
                    'type' => 'fuzzy_name',
                    'confidence' => $similarity,
                    'metadata' => [
                        'blueprint_name' => $blueprint->name,
                        'card_name' => $card->name,
                        'similarity_score' => $similarity
                    ]
                ];
            }
        }

        // Sort by confidence (highest first)
        usort($matches, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        // Return only the best match to avoid false positives
        return !empty($matches) ? [$matches[0]] : [];
    }

    /**
     * Store matches in the database.
     */
    private function storeMatches(Blueprint $blueprint, array $matches): void
    {
        // If this is a force re-sync, first remove existing relationships
        if ($this->option('force')) {
            $blueprint->oneLogCards()->detach();
        }

        $isResync = $this->option('resync') || $this->option('force');

        foreach ($matches as $match) {
            try {
                // Check if relationship already exists to avoid duplicates
                $existingRelation = $blueprint->oneLogCards()
                    ->where('onelog_card_id', $match['card']->id)
                    ->exists();

                if (!$existingRelation) {
                    $blueprint->oneLogCards()->attach($match['card']->id, [
                        'match_type' => $match['type'],
                        'confidence_score' => $match['confidence'],
                        'match_metadata' => json_encode($match['metadata']),
                        'is_verified' => $match['confidence'] >= 0.95, // Auto-verify high confidence matches
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info('Blueprint matched with OneLog card', [
                        'blueprint_id' => $blueprint->id,
                        'onelog_card_id' => $match['card']->id,
                        'match_type' => $match['type'],
                        'confidence' => $match['confidence'],
                        'action' => 'created'
                    ]);
                } else {
                    // Update existing relationship with new data
                    $blueprint->oneLogCards()->updateExistingPivot($match['card']->id, [
                        'match_type' => $match['type'],
                        'confidence_score' => $match['confidence'],
                        'match_metadata' => json_encode($match['metadata']),
                        'is_verified' => $match['confidence'] >= 0.95,
                        'updated_at' => now(),
                    ]);

                    Log::info('Blueprint relationship updated', [
                        'blueprint_id' => $blueprint->id,
                        'onelog_card_id' => $match['card']->id,
                        'match_type' => $match['type'],
                        'confidence' => $match['confidence'],
                        'action' => 'updated'
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to store/update match', [
                    'blueprint_id' => $blueprint->id,
                    'onelog_card_id' => $match['card']->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Normalize name for comparison.
     */
    private function normalizeName(string $name): string
    {
        $normalized = strtolower($name);
        $normalized = str_replace(['.', ',', '!', '?', '"', "'", '(', ')'], '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Extract base serial (remove variant suffixes).
     */
    private function extractBaseSerial(string $serial): string
    {
        return preg_replace('/_p\d+$|_parallel$|_alt$|_special$/', '', $serial);
    }

    /**
     * Extract expansion code from collector number.
     */
    private function extractExpansionCode(string $collectorNumber): ?string
    {
        return preg_match('/^([A-Z]+\d+)/', $collectorNumber, $matches) ? $matches[1] : null;
    }

    /**
     * Calculate name similarity using Levenshtein distance.
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        if ($name1 === $name2) {
            return 1.0;
        }

        $maxLength = max(strlen($name1), strlen($name2));
        if ($maxLength === 0) {
            return 0.0;
        }

        $distance = levenshtein($name1, $name2);
        return 1.0 - ($distance / $maxLength);
    }

    /**
     * Show matching statistics.
     */
    private function showMatchingStats(): int
    {
        $totalBlueprints = Blueprint::count();
        $matchedBlueprints = Blueprint::whereHas('oneLogCards')->count();
        $unmatchedBlueprints = $totalBlueprints - $matchedBlueprints;

        $totalOneLogCards = OneLogCard::count();
        $matchedOneLogCards = OneLogCard::whereHas('blueprints')->count();
        $unmatchedOneLogCards = $totalOneLogCards - $matchedOneLogCards;

        $this->info('📊 Matching Statistics:');
        $this->newLine();

        $this->info("Blueprints:");
        $this->info("  Total: {$totalBlueprints}");
        $this->info("  Matched: {$matchedBlueprints}");
        $this->info("  Unmatched: {$unmatchedBlueprints}");

        if ($totalBlueprints > 0) {
            $blueprintMatchRate = round(($matchedBlueprints / $totalBlueprints) * 100, 1);
            $this->info("  Match rate: {$blueprintMatchRate}%");
        }

        $this->newLine();

        $this->info("OneLog Cards:");
        $this->info("  Total: {$totalOneLogCards}");
        $this->info("  Matched: {$matchedOneLogCards}");
        $this->info("  Unmatched: {$unmatchedOneLogCards}");

        if ($totalOneLogCards > 0) {
            $cardMatchRate = round(($matchedOneLogCards / $totalOneLogCards) * 100, 1);
            $this->info("  Usage rate: {$cardMatchRate}%");
        }

        // Show match types distribution
        $this->newLine();
        $this->info("Match Types:");

        $matchTypes = DB::table('blueprint_onelog_relations')
            ->select('match_type', DB::raw('count(*) as count'))
            ->groupBy('match_type')
            ->get();

        foreach ($matchTypes as $type) {
            $this->info("  {$type->match_type}: {$type->count}");
        }

        return Command::SUCCESS;
    }

    /**
     * Show products that are excluded from matching.
     */
    private function showExcludedProducts(BlueprintClassificationService $classificationService): int
    {
        $this->info('📋 Products excluded from matching:');
        $this->newLine();

        // Use database classifications if available, fallback to runtime classification
        $totalBlueprints = Blueprint::count();
        $withClassification = Blueprint::whereNotNull('product_type')->count();

        if ($withClassification > 0) {
            $this->info("💾 Using database classifications ({$withClassification}/{$totalBlueprints} classified)");
            $this->showDatabaseClassificationStats();
        } else {
            $this->info("🔄 Using runtime classification (slower)");
            $this->showRuntimeClassificationStats($classificationService);
        }

        return Command::SUCCESS;
    }

    /**
     * Show classification stats from database.
     */
    private function showDatabaseClassificationStats(): void
    {
        $stats = Blueprint::selectRaw('
            product_type,
            ai_classification,
            COUNT(*) as count,
            AVG(ai_confidence_score) as avg_confidence
        ')
            ->whereNotNull('product_type')
            ->groupBy('product_type', 'ai_classification')
            ->get();

        $singleCards = $stats->where('product_type', 'single_card')->sum('count');
        $products = $stats->whereIn('product_type', ['product', 'unknown'])->sum('count');

        // Show excluded products with examples
        $excludedBlueprints = Blueprint::whereIn('product_type', ['product', 'unknown'])
            ->orderBy('ai_confidence_score', 'desc')
            ->limit(10)
            ->get();

        if ($excludedBlueprints->isNotEmpty()) {
            $this->info("📦 Excluded products (top examples by confidence):");
            foreach ($excludedBlueprints->take(5) as $blueprint) {
                $confidence = $blueprint->ai_confidence_score ?? 'N/A';
                $type = $blueprint->ai_classification ?? $blueprint->product_type;
                $this->line("  - {$blueprint->name} ({$type}, confidence: {$confidence})");
            }

            if ($excludedBlueprints->count() > 5) {
                $this->line("  ... and " . ($excludedBlueprints->count() - 5) . " more");
            }
        }

        $this->newLine();
        $this->info("📊 Database Classification Summary:");
        $this->info("  Single cards (will be matched): {$singleCards}");
        $this->info("  Products (excluded): {$products}");

        $total = $singleCards + $products;
        if ($total > 0) {
            $excludeRate = round(($products / $total) * 100, 1);
            $this->info("  Exclusion rate: {$excludeRate}%");
        }

        // Show confidence distribution
        $highConfidence = Blueprint::where('ai_confidence_score', '>=', 0.8)->count();
        $mediumConfidence = Blueprint::whereBetween('ai_confidence_score', [0.5, 0.79])->count();
        $lowConfidence = Blueprint::where('ai_confidence_score', '<', 0.5)->whereNotNull('ai_confidence_score')->count();

        $this->newLine();
        $this->info("🎯 Confidence Distribution:");
        $this->info("  High (≥0.8): {$highConfidence}");
        $this->info("  Medium (0.5-0.79): {$mediumConfidence}");
        $this->info("  Low (<0.5): {$lowConfidence}");
    }

    /**
     * Show classification stats using runtime classification.
     */
    private function showRuntimeClassificationStats(BlueprintClassificationService $classificationService): void
    {
        // Classify all blueprints
        $results = $classificationService->bulkClassifyBlueprints();

        $stats = [
            'single_card' => [],
            'likely_single_card' => [],
            'product' => [],
            'likely_product' => [],
            'unknown' => [],
        ];

        foreach ($results as $result) {
            $classification = $result['classification'];
            if (isset($stats[$classification])) {
                $stats[$classification][] = $result;
            }
        }

        // Show excluded (products)
        $excludedTypes = ['product', 'likely_product'];
        $totalExcluded = 0;

        foreach ($excludedTypes as $type) {
            $count = count($stats[$type]);
            $totalExcluded += $count;

            if ($count > 0) {
                $this->info("📦 {$type}: {$count}");

                // Show examples
                $examples = array_slice($stats[$type], 0, 5);
                foreach ($examples as $example) {
                    $score = $example['analysis']['analysis_score'];
                    $this->line("  - {$example['name']} (score: " . number_format($score, 2) . ")");
                }

                if ($count > 5) {
                    $this->line("  ... and " . ($count - 5) . " more");
                }
                $this->newLine();
            }
        }

        // Show included (single cards)
        $includedTypes = ['single_card', 'likely_single_card'];
        $totalIncluded = 0;

        foreach ($includedTypes as $type) {
            $count = count($stats[$type]);
            $totalIncluded += $count;

            if ($count > 0) {
                $this->info("🃏 {$type}: {$count}");
            }
        }

        $unknownCount = count($stats['unknown']);

        $this->newLine();
        $this->info("📊 Runtime Classification Summary:");
        $this->info("  Total blueprints: " . count($results));
        $this->info("  Single cards (will be matched): {$totalIncluded}");
        $this->info("  Products (excluded): {$totalExcluded}");
        $this->info("  Unknown/unclear: {$unknownCount}");

        if (count($results) > 0) {
            $excludeRate = round(($totalExcluded / count($results)) * 100, 1);
            $this->info("  Exclusion rate: {$excludeRate}%");
        }

        $this->newLine();
        $this->info('💡 Run php artisan itsmint:classify-blueprints --save to improve performance');
    }

    /**
     * Debug matching for a specific serial.
     */
    private function debugSerialMatching(string $serial): int
    {
        $this->info("🔍 Debugging matching for serial: {$serial}");
        $this->newLine();

        // Get blueprints with this collector number
        $blueprints = Blueprint::whereRaw("properties->'fixed_properties'->>'collector_number' = ?", [$serial])->get();

        if ($blueprints->isEmpty()) {
            $this->warn("No blueprints found with collector number: {$serial}");
            return Command::SUCCESS;
        }

        $this->info("📋 Found {$blueprints->count()} blueprint(s):");
        foreach ($blueprints as $blueprint) {
            $this->line("  - ID: {$blueprint->id}, Name: {$blueprint->name}, Rarity: {$blueprint->rarity}, Version: '{$blueprint->version}', Promo: " . ($blueprint->is_promo_card ? 'Yes' : 'No'));
        }

        $this->newLine();

        // Get OneLog cards with this serial
        $oneLogCards = OneLogCard::where('serial', $serial)->get();

        if ($oneLogCards->isEmpty()) {
            $this->warn("No OneLog cards found with serial: {$serial}");
            return Command::SUCCESS;
        }

        $this->info("🃏 Found {$oneLogCards->count()} OneLog card(s):");
        foreach ($oneLogCards as $card) {
            $this->line("  - ID: {$card->id}, Name: {$card->name}, Rarity: {$card->rarity}, Variant: " . ($card->is_variant ? 'Yes' : 'No') . ", Promo: " . ($card->is_promo ? 'Yes' : 'No') . ", IMG_ID: {$card->img_id}");
        }

        $this->newLine();

        // Test matching for each blueprint
        foreach ($blueprints as $blueprint) {
            $this->info("🔗 Testing matches for Blueprint #{$blueprint->id} ({$blueprint->name}):");

            $matches = $this->findExactMatches($blueprint);

            if (empty($matches)) {
                $this->line("  ❌ No matches found");
            } else {
                $this->line("  ✅ Found {" . count($matches) . "} match(es):");

                foreach ($matches as $match) {
                    $card = $match['card'];
                    $confidence = number_format($match['confidence'], 3);
                    $metadata = $match['metadata'];

                    $this->line("    - OneLog Card #{$card->id}: {$card->name} (confidence: {$confidence})");
                    $this->line("      Rarities: Blueprint='{$metadata['blueprint_rarity']}' vs Card='{$metadata['card_rarity']}'");
                    $this->line("      Promo: Blueprint=" . ($metadata['blueprint_is_promo'] ? 'Yes' : 'No') . " vs Card=" . ($metadata['card_is_promo'] ? 'Yes' : 'No'));
                    $this->line("      Variant: Card=" . ($metadata['card_is_variant'] ? 'Yes' : 'No'));
                    $this->line("      Version: Blueprint='{$metadata['blueprint_version']}' vs CardSets='{$metadata['card_sets']}'");
                    $this->line("      Score breakdown:");
                    foreach ($metadata['score_breakdown'] as $component => $score) {
                        $formattedScore = is_numeric($score) ? number_format($score, 3) : ($score ? 'Yes' : 'No');
                        $this->line("        {$component}: {$formattedScore}");
                    }
                    $this->newLine();
                }
            }
        }

        return Command::SUCCESS;
    }
}
