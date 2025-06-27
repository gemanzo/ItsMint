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
                            {--force : Re-process existing matches}
                            {--expansion= : Match specific expansion only}
                            {--show-stats : Show matching statistics}
                            {--show-excluded : Show products excluded from matching}';

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
                $this->info("✅ Stored {$totalMatches} matches");
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

        if (!$this->option('force')) {
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
     * Find exact collector number matches.
     */
    private function findExactMatches(Blueprint $blueprint): array
    {
        $collectorNumber = $blueprint->collector_number;

        if (!$collectorNumber) {
            return [];
        }

        $cards = OneLogCard::where('serial', $collectorNumber)->get();

        return $cards->map(function ($card) {
            return [
                'card' => $card,
                'type' => 'exact_serial',
                'confidence' => 1.0,
                'metadata' => ['match_field' => 'serial']
            ];
        })->toArray();
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
        foreach ($matches as $match) {
            try {
                $blueprint->oneLogCards()->attach($match['card']->id, [
                    'match_type' => $match['type'],
                    'confidence_score' => $match['confidence'],
                    'match_metadata' => json_encode($match['metadata']),
                    'is_verified' => $match['confidence'] >= 0.95, // Auto-verify high confidence matches
                ]);

                Log::info('Blueprint matched with OneLog card', [
                    'blueprint_id' => $blueprint->id,
                    'onelog_card_id' => $match['card']->id,
                    'match_type' => $match['type'],
                    'confidence' => $match['confidence']
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to store match', [
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
}
