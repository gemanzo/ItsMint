<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\OneLogCard;
use App\Services\BlueprintClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                            {--debug-serial= : Debug specific serial matching}
                            {--validate-existing : Validate existing matches with new quality standards}
                            {--quality-report : Show match quality report with new standards}';

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
        if ($this->option('show-stats')) {
            return $this->showMatchingStats();
        }

        if ($this->option('show-excluded')) {
            return $this->showExcludedProducts($classificationService);
        }

        if ($debugSerial = $this->option('debug-serial')) {
            return $this->debugSerialMatching($debugSerial);
        }

        if ($this->option('validate-existing')) {
            return $this->validateExistingMatches();
        }

        if ($this->option('quality-report')) {
            return $this->showQualityReport();
        }

        try {
            $totalMatches = 0;

            // Get blueprints to process
            $blueprints = $this->getBlueprintsToProcess($classificationService);

            if ($blueprints->isEmpty()) {
                $this->warn('No blueprints to process');

                return Command::SUCCESS;
            }

            $this->info('🔧 Using reinforced matching logic with stricter validation');
            $this->info("📊 Processing {$blueprints->count()} blueprints with improved quality standards");
            $this->newLine();

            $progressBar = $this->output->createProgressBar($blueprints->count());

            foreach ($blueprints as $blueprint) {
                $matches = $this->findMatches($blueprint);

                if (! empty($matches)) {
                    if ($this->option('detailed')) {
                        $this->newLine();
                        $this->line("Blueprint #{$blueprint->id}: {$blueprint->name} (collector: {$blueprint->collector_number})");
                        foreach ($matches as $match) {
                            $confidence = number_format($match['confidence'], 3);
                            $this->line("  → Matched with OneLog #{$match['card']->id}: {$match['card']->name} (confidence: {$confidence}, type: {$match['type']})");
                        }
                    }

                    if (! $this->option('dry-run')) {
                        $this->storeMatches($blueprint, $matches);
                    }
                    $totalMatches += count($matches);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info('✅ Matching completed with reinforced quality standards');
            $this->info("📈 Total high-quality matches found: {$totalMatches}");

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

        if (! $this->option('force') && ! $this->option('resync')) {
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

        if (! $collectorNumber) {
            return [];
        }

        $matches = [];
        $blueprintRarity = $blueprint->rarity;

        // PRIMO: Prova match esatto
        $exactCards = OneLogCard::where('serial', $collectorNumber)->get();

        foreach ($exactCards as $card) {
            $matchScore = $this->calculateVersionMatchScore($blueprint, $card, $blueprintRarity);

            if ($matchScore >= 0.7) {
                $matches[] = [
                    'card' => $card,
                    'type' => 'exact_serial',
                    'confidence' => $matchScore,
                    'metadata' => [
                        'match_field' => 'serial',
                        'match_type' => 'exact_collector_number',
                        'blueprint_collector_number' => $collectorNumber,
                        'card_serial' => $card->serial,
                        'blueprint_rarity' => $blueprintRarity,
                        'card_rarity' => $card->rarity,
                        'blueprint_is_promo' => $blueprint->is_promo_card,
                        'card_is_promo' => $card->is_promo,
                        'card_is_variant' => $card->is_variant,
                        'blueprint_version' => $blueprint->version,
                        'card_sets' => $card->card_sets,
                        'score_breakdown' => $this->getScoreBreakdown($blueprint, $card, $blueprintRarity),
                    ],
                ];
            }
        }

        // SECONDO: Se non trova match esatti E il collector_number ha suffissi variant, prova variant matching
        if (empty($matches) && $this->hasVariantSuffix($collectorNumber)) {
            $variantMatches = $this->findVariantMatches($blueprint, $collectorNumber, $blueprintRarity);
            $matches = array_merge($matches, $variantMatches);
        }

        // Sort by confidence (highest first)
        usort($matches, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        // Return top match(es) - se più match hanno score alto simile, prendili tutti
        if (! empty($matches)) {
            $topScore = $matches[0]['confidence'];

            // Riduco la varianza accettabile per essere più selettivi
            return array_filter($matches, fn($match) => $match['confidence'] >= $topScore - 0.02);
        }

        return [];
    }

    /**
     * Check if collector number has variant suffix.
     */
    private function hasVariantSuffix(string $collectorNumber): bool
    {
        // Pattern per suffissi variant: a, b, c, m, p, alt, parallel, etc.
        return preg_match('/^([A-Z]+\d*-\d+)([a-z]|alt|parallel|promo|manga|p\d*)$/i', $collectorNumber) === 1;
    }

    /**
     * Find variant matches for blueprints with variant suffixes.
     */
    private function findVariantMatches(Blueprint $blueprint, string $collectorNumber, ?string $blueprintRarity): array
    {
        $matches = [];

        // Estrai base serial e suffisso
        $baseSerial = $this->extractBaseSerial($collectorNumber);
        $variantSuffix = $this->extractVariantSuffix($collectorNumber);

        if (! $baseSerial || ! $variantSuffix) {
            return [];
        }

        // Cerca carte OneLog con base serial corrispondente
        $cards = OneLogCard::where('serial', $baseSerial)
            ->orWhere('base_serial', $baseSerial)
            ->get();

        foreach ($cards as $card) {
            // Valuta se questa carta OneLog corrisponde al tipo di variante della blueprint
            $variantMatchScore = $this->calculateVariantTypeMatchScore($blueprint, $card, $variantSuffix);

            if ($variantMatchScore > 0) {
                $totalScore = $this->calculateVersionMatchScore($blueprint, $card, $blueprintRarity);

                // Bonus per variant type matching corretto
                $totalScore += $variantMatchScore * 0.1;

                // Ensure score is between 0 and 1
                $totalScore = max(0.0, min(1.0, $totalScore));

                if ($totalScore >= 0.7) { // SOGLIA MANTENUTA RIGOROSA
                    $matches[] = [
                        'card' => $card,
                        'type' => 'variant_serial',
                        'confidence' => $totalScore,
                        'metadata' => [
                            'match_field' => 'variant_serial',
                            'match_type' => 'variant_collector_number',
                            'blueprint_collector_number' => $collectorNumber,
                            'card_serial' => $card->serial,
                            'base_serial' => $baseSerial,
                            'variant_suffix' => $variantSuffix,
                            'variant_match_score' => $variantMatchScore,
                            'blueprint_rarity' => $blueprintRarity,
                            'card_rarity' => $card->rarity,
                            'blueprint_is_promo' => $blueprint->is_promo_card,
                            'card_is_promo' => $card->is_promo,
                            'card_is_variant' => $card->is_variant,
                            'blueprint_version' => $blueprint->version,
                            'card_sets' => $card->card_sets,
                            'score_breakdown' => $this->getScoreBreakdown($blueprint, $card, $blueprintRarity),
                        ],
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * Extract variant suffix from collector number.
     */
    private function extractVariantSuffix(string $collectorNumber): ?string
    {
        if (preg_match('/^([A-Z]+\d*-\d+)([a-z]|alt|parallel|promo|manga|p\d*)$/i', $collectorNumber, $matches)) {
            return strtolower($matches[2]);
        }

        return null;
    }

    /**
     * Calculate variant type match score between blueprint and OneLog card.
     */
    private function calculateVariantTypeMatchScore(Blueprint $blueprint, OneLogCard $card, string $variantSuffix): float
    {
        $blueprintVersion = strtolower(trim($blueprint->version ?? ''));
        $cardSets = strtolower(trim($card->card_sets ?? ''));

        // Mappings tra suffissi e tipi di variante
        $variantMappings = [
            'a' => ['alternative', 'alternate', 'alt'],
            'm' => ['manga', 'panel'],
            'p' => ['promo', 'promotional'],
            'alt' => ['alternative', 'alternate'],
            'parallel' => ['parallel', 'variant'],
            'promo' => ['promo', 'promotional'],
            'manga' => ['manga', 'panel'],
        ];

        $expectedKeywords = $variantMappings[$variantSuffix] ?? [];

        if (empty($expectedKeywords)) {
            return 0.5; // Neutrale per suffissi sconosciuti
        }

        $score = 0.0;

        // Check blueprint version
        foreach ($expectedKeywords as $keyword) {
            if (str_contains($blueprintVersion, $keyword)) {
                $score += 0.6; // Alto punteggio per version match
                break;
            }
        }

        // Check card sets
        foreach ($expectedKeywords as $keyword) {
            if (str_contains($cardSets, $keyword)) {
                $score += 0.4; // Punteggio addizionale per card_sets match
                break;
            }
        }

        // Bonus se il card è marcato come variant per suffissi che lo dovrebbero essere
        if (in_array($variantSuffix, ['a', 'm', 'alt', 'parallel']) && $card->is_variant) {
            $score += 0.2;
        }

        // Penalty se non è variant ma dovrebbe esserlo
        if (in_array($variantSuffix, ['a', 'm', 'alt', 'parallel']) && ! $card->is_variant) {
            $score -= 0.2;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Calculate match score between blueprint and OneLog card considering variants, promos, and rarity.
     */
    private function calculateVersionMatchScore(Blueprint $blueprint, OneLogCard $card, ?string $blueprintRarity): float
    {
        $score = 0.0;

        // Base score for serial match - AUMENTATO per dare più peso al match esatto
        $score += 0.5;

        // Rarity matching (critical for distinguishing versions) - PESO AUMENTATO
        $rarityScore = $this->calculateRarityMatchScore($blueprintRarity, $card->rarity);
        $score += $rarityScore * 0.35;

        // Promo status matching - PESO AUMENTATO per penalizzare mismatch
        $isPromoMatch = $blueprint->is_promo_card === $card->is_promo;
        if ($isPromoMatch) {
            $score += 0.15;
        } else {
            // Penalize promo mismatch more heavily
            $score -= 0.25; // PENALTY AUMENTATA
        }

        // Version/variant logic
        $variantScore = $this->calculateVariantMatchScore($blueprint, $card);
        $score += $variantScore * 0.05;

        // Version/card_sets matching (importante per set speciali)
        $versionSetsScore = $this->calculateVersionSetsMatchScore($blueprint, $card);
        $score += $versionSetsScore * 0.1;

        // Name matching (fallback verification) - PESO RIDOTTO
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
        if (! $blueprintRarity) {
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
            'Special' => ['L', 'SR', 'R', 'SP CARD', 'SEC', 'C'], // Special cards
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
        $hasSpecialVersion = ! empty($blueprintVersion) && $blueprintVersion !== '';

        // If blueprint has special version info and card is variant/parallel
        if ($hasSpecialVersion && $card->is_variant) {
            return 1.0;
        }

        // If blueprint has no special version and card is not variant (base version)
        if (! $hasSpecialVersion && ! $card->is_variant) {
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

        if (! empty($significantMatches)) {
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
            return strlen($word) >= 3 && ! in_array($word, $stopWords) && ! is_numeric($word);
        });

        return array_values($keywords);
    }

    /**
     * Get detailed score breakdown for debugging.
     */
    private function getScoreBreakdown(Blueprint $blueprint, OneLogCard $card, ?string $blueprintRarity): array
    {
        return [
            'serial_match' => 0.5,
            'rarity_score' => $this->calculateRarityMatchScore($blueprintRarity, $card->rarity),
            'promo_match' => $blueprint->is_promo_card === $card->is_promo,
            'variant_score' => $this->calculateVariantMatchScore($blueprint, $card),
            'version_sets_score' => $this->calculateVersionSetsMatchScore($blueprint, $card),
            'name_similarity' => $this->calculateNameSimilarity(
                $this->normalizeName($blueprint->name),
                $this->normalizeName($card->name)
            ),
        ];
    }

    /**
     * Get detailed match criteria for debugging and validation.
     */
    private function getMatchCriteria(Blueprint $blueprint, OneLogCard $card, ?string $blueprintRarity): array
    {
        return [
            'serial_exact_match' => $blueprint->collector_number === $card->serial,
            'blueprint_collector_number' => $blueprint->collector_number,
            'card_serial' => $card->serial,
            'rarity_match' => $blueprintRarity === $card->rarity,
            'blueprint_rarity' => $blueprintRarity,
            'card_rarity' => $card->rarity,
            'promo_match' => $blueprint->is_promo_card === $card->is_promo,
            'blueprint_is_promo' => $blueprint->is_promo_card,
            'card_is_promo' => $card->is_promo,
            'variant_logic' => [
                'blueprint_has_version' => ! empty(trim($blueprint->version ?? '')),
                'card_is_variant' => $card->is_variant,
                'blueprint_version' => $blueprint->version,
                'card_sets' => $card->card_sets,
            ],
            'name_similarity' => $this->calculateNameSimilarity(
                $this->normalizeName($blueprint->name),
                $this->normalizeName($card->name)
            ),
            'normalized_names' => [
                'blueprint' => $this->normalizeName($blueprint->name),
                'card' => $this->normalizeName($card->name),
            ],
        ];
    }

    /**
     * Find base serial matches (for variants).
     */
    private function findBaseSerialMatches(Blueprint $blueprint): array
    {
        $collectorNumber = $blueprint->collector_number;

        if (! $collectorNumber) {
            return [];
        }

        // Check if blueprint might be a variant
        $baseSerial = $this->extractBaseSerial($collectorNumber);

        $cards = OneLogCard::where('base_serial', $baseSerial)
            ->orWhere('serial', $baseSerial)
            ->get();

        $matches = [];

        foreach ($cards as $card) {
            // VALIDAZIONE RIGOROSA: Verifica che ci sia una corrispondenza logica
            $isBaseSerialMatch = $card->base_serial === $baseSerial;
            $isDirectSerialMatch = $card->serial === $baseSerial;

            if (! $isBaseSerialMatch && ! $isDirectSerialMatch) {
                continue;
            }

            // Calcola confidence più rigorosamente
            $confidence = $card->serial === $collectorNumber ? 0.95 : 0.75; // SOGLIE PIÙ CONSERVATIVE

            // SOGLIA MINIMA per base serial match
            if ($confidence >= 0.75) {
                $matches[] = [
                    'card' => $card,
                    'type' => 'base_serial',
                    'confidence' => $confidence,
                    'metadata' => [
                        'blueprint_serial' => $collectorNumber,
                        'matched_base_serial' => $baseSerial,
                        'card_serial' => $card->serial,
                        'card_base_serial' => $card->base_serial,
                        'match_type' => $isDirectSerialMatch ? 'direct_base_match' : 'base_serial_match',
                    ],
                ];
            }
        }

        // Sort by confidence (highest first)
        usort($matches, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $matches;
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

        // Try exact name match first CON VALIDAZIONE AGGIUNTIVA
        $exactNameCards = OneLogCard::whereRaw('LOWER(name) = ?', [strtolower($blueprint->name)])->get();

        foreach ($exactNameCards as $card) {
            // VALIDAZIONE AGGIUNTIVA: Verifica che il nome non sia troppo generico
            if (strlen(trim($blueprint->name)) < 3) {
                Log::warning('Name too short for reliable matching', [
                    'blueprint_id' => $blueprint->id,
                    'blueprint_name' => $blueprint->name,
                    'name_length' => strlen(trim($blueprint->name)),
                ]);

                continue;
            }

            $matches[] = [
                'card' => $card,
                'type' => 'exact_name',
                'confidence' => 0.85, // CONFIDENCE RIDOTTA per name match
                'metadata' => [
                    'matched_name' => $card->name,
                    'blueprint_name' => $blueprint->name,
                    'normalized_blueprint_name' => $blueprintName,
                    'normalized_card_name' => $this->normalizeName($card->name),
                ],
            ];
        }

        // If no exact matches, try fuzzy matching CON SOGLIE PIÙ RIGOROSE
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

        // VALIDAZIONE: Nome non troppo corto per fuzzy matching
        if (strlen(trim($blueprint->name)) < 4) {

            return [];
        }

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

            // SOGLIA PIÙ RIGOROSA per fuzzy matching
            if ($similarity >= 0.85) { // AUMENTATO da 0.7 a 0.85
                $confidence = $similarity * 0.7; // RIDOTTO il moltiplicatore per essere più conservativi

                if ($confidence >= 0.6) { // SOGLIA MINIMA per accettare fuzzy match
                    $matches[] = [
                        'card' => $card,
                        'type' => 'fuzzy_name',
                        'confidence' => $confidence,
                        'metadata' => [
                            'blueprint_name' => $blueprint->name,
                            'card_name' => $card->name,
                            'similarity_score' => $similarity,
                            'normalized_blueprint_name' => $blueprintName,
                            'normalized_card_name' => $cardName,
                        ],
                    ];
                }
            }
        }

        // Sort by confidence (highest first)
        usort($matches, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        // Return only the best match to avoid false positives - PIÙ CONSERVATIVO
        return ! empty($matches) ? [$matches[0]] : [];
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
                // VALIDAZIONE FINALE: Doppio controllo della qualità del match
                $isValidMatch = $this->validateMatchQuality($blueprint, $match);

                if (! $isValidMatch) {
                    Log::warning('Match failed final validation', [
                        'blueprint_id' => $blueprint->id,
                        'blueprint_collector_number' => $blueprint->collector_number,
                        'onelog_card_id' => $match['card']->id,
                        'card_serial' => $match['card']->serial,
                        'match_type' => $match['type'],
                        'confidence' => $match['confidence'],
                        'reason' => 'Failed quality validation',
                    ]);

                    continue;
                }

                // Check if relationship already exists to avoid duplicates
                $existingRelation = $blueprint->oneLogCards()
                    ->where('onelog_card_id', $match['card']->id)
                    ->exists();

                if (! $existingRelation) {
                    $blueprint->oneLogCards()->attach($match['card']->id, [
                        'match_type' => $match['type'],
                        'confidence_score' => $match['confidence'],
                        'match_metadata' => json_encode($match['metadata']),
                        'is_verified' => $match['confidence'] >= 0.95, // Auto-verify high confidence matches
                        'created_at' => now(),
                        'updated_at' => now(),
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
                }
            } catch (\Exception $e) {
                Log::error('Failed to store/update match', [
                    'blueprint_id' => $blueprint->id,
                    'blueprint_collector_number' => $blueprint->collector_number,
                    'onelog_card_id' => $match['card']->id,
                    'match_type' => $match['type'],
                    'confidence' => $match['confidence'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Validate match quality before storing.
     */
    private function validateMatchQuality(Blueprint $blueprint, array $match): bool
    {
        $card = $match['card'];
        $confidence = $match['confidence'];
        $type = $match['type'];

        // Base confidence check
        if ($confidence < 0.6) {
            return false;
        }

        // Per exact_serial match, verifica rigorosa
        if ($type === 'exact_serial') {
            // CRITICO: collector_number DEVE matchare esattamente con serial
            if ($blueprint->collector_number !== $card->serial) {
                Log::error('Critical validation failure: collector_number mismatch in exact_serial match', [
                    'blueprint_id' => $blueprint->id,
                    'blueprint_collector_number' => $blueprint->collector_number,
                    'card_serial' => $card->serial,
                    'onelog_card_id' => $card->id,
                ]);

                return false;
            }

            // Per exact match, confidence minima deve essere alta
            if ($confidence < 0.7) {
                return false;
            }
        }

        // Per variant_serial match, validazione specifica per varianti
        if ($type === 'variant_serial') {
            if ($confidence < 0.7) { // Soglia alta anche per variant
                return false;
            }

            $metadata = $match['metadata'];
            $collectorNumber = $blueprint->collector_number;
            $baseSerial = $metadata['base_serial'] ?? null;
            $variantSuffix = $metadata['variant_suffix'] ?? null;

            // Verifica che il collector_number abbia effettivamente un suffisso variant
            if (! $this->hasVariantSuffix($collectorNumber)) {
                Log::warning('Variant match attempted on non-variant collector number', [
                    'blueprint_id' => $blueprint->id,
                    'collector_number' => $collectorNumber,
                ]);

                return false;
            }

            // Verifica che base serial e variant suffix siano stati estratti correttamente
            if (! $baseSerial || ! $variantSuffix) {
                Log::warning('Failed to extract base serial or variant suffix', [
                    'blueprint_id' => $blueprint->id,
                    'collector_number' => $collectorNumber,
                    'base_serial' => $baseSerial,
                    'variant_suffix' => $variantSuffix,
                ]);

                return false;
            }

            // Verifica che ci sia una connessione logica tra blueprint e card
            if ($card->serial !== $baseSerial && $card->base_serial !== $baseSerial) {
                Log::warning('No logical connection between blueprint and card serials in variant match', [
                    'blueprint_id' => $blueprint->id,
                    'collector_number' => $collectorNumber,
                    'base_serial' => $baseSerial,
                    'card_serial' => $card->serial,
                    'card_base_serial' => $card->base_serial,
                ]);

                return false;
            }

            // Verifica che il variant match score sia ragionevole
            $variantMatchScore = $metadata['variant_match_score'] ?? 0;
            if ($variantMatchScore <= 0.2) { // Soglia minima per variant type matching
                Log::warning('Variant match rejected due to low variant type score', [
                    'blueprint_id' => $blueprint->id,
                    'collector_number' => $collectorNumber,
                    'variant_match_score' => $variantMatchScore,
                    'blueprint_version' => $blueprint->version,
                    'card_sets' => $card->card_sets,
                ]);

                return false;
            }
        }

        // Per base_serial match, verifica logica
        if ($type === 'base_serial') {
            if ($confidence < 0.75) {
                return false;
            }

            // Verifica che ci sia una relazione logica tra i seriali
            $baseSerial = $this->extractBaseSerial($blueprint->collector_number ?? '');
            if ($card->base_serial !== $baseSerial && $card->serial !== $baseSerial) {
                return false;
            }
        }

        // Per name match, soglie ancora più rigorose
        if (in_array($type, ['exact_name', 'fuzzy_name'])) {
            if ($confidence < 0.6) {
                return false;
            }

            // Verifica che il nome non sia troppo generico
            if (strlen(trim($blueprint->name)) < 3) {
                return false;
            }
        }

        return true;
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
        // Nuova logica migliorata per estrarre base serial da vari pattern di varianti

        // Pattern 1: Suffissi singola lettera (a, b, c, m, p, etc.) - es: OP01-120a -> OP01-120
        if (preg_match('/^([A-Z]+\d*-\d+)[a-z]$/i', $serial, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Suffissi multiple lettere (alt, parallel, promo, manga) - es: OP01-120alt -> OP01-120
        if (preg_match('/^([A-Z]+\d*-\d+)(alt|parallel|promo|manga)$/i', $serial, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Suffissi con numeri (p1, p2, etc.) - es: OP01-120p1 -> OP01-120
        if (preg_match('/^([A-Z]+\d*-\d+)p\d+$/i', $serial, $matches)) {
            return $matches[1];
        }

        // Pattern 4: Vecchi pattern esistenti (per compatibilità)
        $processed = preg_replace('/_p\d+$|_parallel$|_alt$|_special$/', '', $serial);

        // Se è cambiato, ritorna il risultato processato
        if ($processed !== $serial) {
            return $processed;
        }

        // Se nessun pattern di variante trovato, ritorna il serial originale
        return $serial;
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

        $this->info('Blueprints:');
        $this->info("  Total: {$totalBlueprints}");
        $this->info("  Matched: {$matchedBlueprints}");
        $this->info("  Unmatched: {$unmatchedBlueprints}");

        if ($totalBlueprints > 0) {
            $blueprintMatchRate = round(($matchedBlueprints / $totalBlueprints) * 100, 1);
            $this->info("  Match rate: {$blueprintMatchRate}%");
        }

        $this->newLine();

        $this->info('OneLog Cards:');
        $this->info("  Total: {$totalOneLogCards}");
        $this->info("  Matched: {$matchedOneLogCards}");
        $this->info("  Unmatched: {$unmatchedOneLogCards}");

        if ($totalOneLogCards > 0) {
            $cardMatchRate = round(($matchedOneLogCards / $totalOneLogCards) * 100, 1);
            $this->info("  Usage rate: {$cardMatchRate}%");
        }

        // Show match types distribution
        $this->newLine();
        $this->info('Match Types:');

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
            $this->info('🔄 Using runtime classification (slower)');
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
            $this->info('📦 Excluded products (top examples by confidence):');
            foreach ($excludedBlueprints->take(5) as $blueprint) {
                $confidence = $blueprint->ai_confidence_score ?? 'N/A';
                $type = $blueprint->ai_classification ?? $blueprint->product_type;
                $this->line("  - {$blueprint->name} ({$type}, confidence: {$confidence})");
            }

            if ($excludedBlueprints->count() > 5) {
                $this->line('  ... and ' . ($excludedBlueprints->count() - 5) . ' more');
            }
        }

        $this->newLine();
        $this->info('📊 Database Classification Summary:');
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
        $this->info('🎯 Confidence Distribution:');
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
                    $this->line("  - {$example['name']} (score: " . number_format($score, 2) . ')');
                }

                if ($count > 5) {
                    $this->line('  ... and ' . ($count - 5) . ' more');
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
        $this->info('📊 Runtime Classification Summary:');
        $this->info('  Total blueprints: ' . count($results));
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

            // NUOVO: Verifica se potrebbe essere una variante
            if ($this->hasVariantSuffix($serial)) {
                $baseSerial = $this->extractBaseSerial($serial);
                $variantSuffix = $this->extractVariantSuffix($serial);
                $this->info('🔄 Detected variant pattern:');
                $this->info("  Base serial: {$baseSerial}");
                $this->info("  Variant suffix: {$variantSuffix}");

                // Cerca blueprint con base serial
                $baseBlueprints = Blueprint::whereRaw("properties->'fixed_properties'->>'collector_number' = ?", [$baseSerial])->get();
                if ($baseBlueprints->isNotEmpty()) {
                    $this->info("📋 Found {$baseBlueprints->count()} base blueprint(s) for base serial {$baseSerial}");
                }

                // Cerca blueprint con pattern simili
                $similarBlueprints = Blueprint::whereRaw("properties->'fixed_properties'->>'collector_number' LIKE ?", ["{$baseSerial}%"])->get();
                if ($similarBlueprints->isNotEmpty()) {
                    $this->info("🔍 Found {$similarBlueprints->count()} similar blueprint(s):");
                    foreach ($similarBlueprints as $bp) {
                        $this->line("  - ID: {$bp->id}, Collector: {$bp->collector_number}, Name: {$bp->name}, Version: '{$bp->version}'");
                    }
                }
            }

            return Command::SUCCESS;
        }

        $this->info("📋 Found {$blueprints->count()} blueprint(s):");
        foreach ($blueprints as $blueprint) {
            $this->line("  - ID: {$blueprint->id}, Name: {$blueprint->name}, Rarity: {$blueprint->rarity}, Version: '{$blueprint->version}', Promo: " . ($blueprint->is_promo_card ? 'Yes' : 'No'));

            // NUOVO: Mostra info variant se applicabile
            if ($this->hasVariantSuffix($blueprint->collector_number)) {
                $baseSerial = $this->extractBaseSerial($blueprint->collector_number);
                $variantSuffix = $this->extractVariantSuffix($blueprint->collector_number);
                $this->line("    🔄 Variant detected - Base: {$baseSerial}, Suffix: {$variantSuffix}");
            }
        }

        $this->newLine();

        // Get OneLog cards with this serial
        $oneLogCards = OneLogCard::where('serial', $serial)->get();

        if ($oneLogCards->isEmpty()) {
            $this->warn("No OneLog cards found with serial: {$serial}");

            // NUOVO: Se è una variante, cerca nella base
            if ($this->hasVariantSuffix($serial)) {
                $baseSerial = $this->extractBaseSerial($serial);
                $this->info("🔄 Searching for base serial matches: {$baseSerial}");

                $baseCards = OneLogCard::where('serial', $baseSerial)
                    ->orWhere('base_serial', $baseSerial)
                    ->get();

                if ($baseCards->isNotEmpty()) {
                    $this->info("🃏 Found {$baseCards->count()} OneLog card(s) for base serial:");
                    foreach ($baseCards as $card) {
                        $this->line("  - ID: {$card->id}, Name: {$card->name}, Serial: {$card->serial}, Base: {$card->base_serial}, Rarity: {$card->rarity}, Variant: " . ($card->is_variant ? 'Yes' : 'No') . ', Promo: ' . ($card->is_promo ? 'Yes' : 'No') . ", Sets: '{$card->card_sets}'");
                    }
                }
            }

            return Command::SUCCESS;
        }

        $this->info("🃏 Found {$oneLogCards->count()} OneLog card(s):");
        foreach ($oneLogCards as $card) {
            $this->line("  - ID: {$card->id}, Name: {$card->name}, Rarity: {$card->rarity}, Variant: " . ($card->is_variant ? 'Yes' : 'No') . ', Promo: ' . ($card->is_promo ? 'Yes' : 'No') . ", IMG_ID: {$card->img_id}, Sets: '{$card->card_sets}'");
        }

        $this->newLine();

        // Test matching for each blueprint
        foreach ($blueprints as $blueprint) {
            $this->info("🔗 Testing matches for Blueprint #{$blueprint->id} ({$blueprint->name}):");

            $matches = $this->findExactMatches($blueprint);

            if (empty($matches)) {
                $this->line('  ❌ No matches found');

                // NUOVO: Test variant matching se appropriato
                if ($this->hasVariantSuffix($blueprint->collector_number)) {
                    $this->line('  🔄 Testing variant matching...');
                    $variantMatches = $this->findVariantMatches($blueprint, $blueprint->collector_number, $blueprint->rarity);

                    if (! empty($variantMatches)) {
                        $this->line('  ✅ Found ' . count($variantMatches) . ' variant match(es):');
                        foreach ($variantMatches as $match) {
                            $card = $match['card'];
                            $confidence = number_format($match['confidence'], 3);
                            $metadata = $match['metadata'];

                            $this->line("    - OneLog Card #{$card->id}: {$card->name} (confidence: {$confidence})");
                            $this->line("      Type: {$match['type']}");
                            $this->line("      Base Serial: {$metadata['base_serial']}");
                            $this->line("      Variant Suffix: {$metadata['variant_suffix']}");
                            $this->line('      Variant Match Score: ' . number_format($metadata['variant_match_score'], 3));
                        }
                    } else {
                        $this->line('  ❌ No variant matches found');
                    }
                }
            } else {
                $this->line('  ✅ Found ' . count($matches) . ' match(es):');

                foreach ($matches as $match) {
                    $card = $match['card'];
                    $confidence = number_format($match['confidence'], 3);
                    $metadata = $match['metadata'];

                    $this->line("    - OneLog Card #{$card->id}: {$card->name} (confidence: {$confidence})");
                    $this->line("      Type: {$match['type']}");
                    $this->line("      Rarities: Blueprint='{$metadata['blueprint_rarity']}' vs Card='{$metadata['card_rarity']}'");
                    $this->line('      Promo: Blueprint=' . ($metadata['blueprint_is_promo'] ? 'Yes' : 'No') . ' vs Card=' . ($metadata['card_is_promo'] ? 'Yes' : 'No'));
                    $this->line('      Variant: Card=' . ($metadata['card_is_variant'] ? 'Yes' : 'No'));
                    $this->line("      Version: Blueprint='{$metadata['blueprint_version']}' vs CardSets='{$metadata['card_sets']}'");

                    // NUOVO: Mostra info variant se presente
                    if (isset($metadata['variant_suffix'])) {
                        $this->line("      Variant Info: Base='{$metadata['base_serial']}', Suffix='{$metadata['variant_suffix']}', Score=" . number_format($metadata['variant_match_score'], 3));
                    }

                    $this->line('      Score breakdown:');
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

    /**
     * Validate existing matches against new quality standards.
     */
    private function validateExistingMatches(): int
    {
        $this->info('🔍 Validating existing blueprint-onelog relationships...');
        $this->newLine();

        $totalBlueprints = Blueprint::count();
        $totalRelations = DB::table('blueprint_onelog_relations')->count();

        $this->info("  Total Blueprints: {$totalBlueprints}");
        $this->info("  Total Blueprint-OneLog Relations: {$totalRelations}");

        $updatedRelations = 0;
        $rejectedRelations = 0;

        $relations = DB::table('blueprint_onelog_relations')
            ->select('id', 'blueprint_id', 'onelog_card_id', 'match_type', 'confidence_score', 'is_verified', 'match_metadata')
            ->get();

        foreach ($relations as $relation) {
            $blueprint = Blueprint::find($relation->blueprint_id);
            $oneLogCard = OneLogCard::find($relation->onelog_card_id);

            if (! $blueprint || ! $oneLogCard) {
                Log::warning('Skipping relation with missing blueprint or OneLog card', [
                    'relation_id' => $relation->id,
                    'blueprint_id' => $relation->blueprint_id,
                    'onelog_card_id' => $relation->onelog_card_id,
                ]);

                continue;
            }

            $matchMetadata = json_decode($relation->match_metadata, true);
            $currentConfidence = $relation->confidence_score;
            $currentVerified = $relation->is_verified;

            $newConfidence = $this->calculateVersionMatchScore($blueprint, $oneLogCard, $blueprint->rarity);
            $newVerified = $newConfidence >= 0.95;

            if ($currentConfidence !== $newConfidence || $currentVerified !== $newVerified) {
                DB::table('blueprint_onelog_relations')
                    ->where('id', $relation->id)
                    ->update([
                        'confidence_score' => $newConfidence,
                        'is_verified' => $newVerified,
                        'updated_at' => now(),
                    ]);
                $updatedRelations++;
            } else {
                $updatedRelations++;
            }
        }

        $this->info("✅ Validation complete. {$updatedRelations} relations updated, {$rejectedRelations} rejected.");
        $this->newLine();
        $this->info('💡 Run php artisan itsmint:match-blueprints-onelog --validate-existing to re-validate all.');

        return Command::SUCCESS;
    }

    /**
     * Show match quality report.
     */
    private function showQualityReport(): int
    {
        $this->info('📊 Match Quality Report (New Standards):');
        $this->newLine();

        $totalRelations = DB::table('blueprint_onelog_relations')->count();
        $totalVerified = DB::table('blueprint_onelog_relations')->where('is_verified', true)->count();
        $totalRejected = DB::table('blueprint_onelog_relations')->where('is_verified', false)->count();

        $this->info("  Total Blueprint-OneLog Relations: {$totalRelations}");
        $this->info("  Verified (≥0.95): {$totalVerified}");
        $this->info("  Rejected (<0.95): {$totalRejected}");

        $this->newLine();
        $this->info('🎯 Confidence Distribution (New Standards):');

        $highConfidence = DB::table('blueprint_onelog_relations')
            ->where('confidence_score', '>=', 0.95)
            ->count();
        $mediumConfidence = DB::table('blueprint_onelog_relations')
            ->whereBetween('confidence_score', [0.7, 0.94])
            ->count();
        $lowConfidence = DB::table('blueprint_onelog_relations')
            ->where('confidence_score', '<', 0.7)
            ->count();

        $this->info("  High (≥0.95): {$highConfidence}");
        $this->info("  Medium (0.7-0.94): {$mediumConfidence}");
        $this->info("  Low (<0.7): {$lowConfidence}");

        $this->newLine();
        $this->info('💡 Run php artisan itsmint:match-blueprints-onelog --quality-report to see detailed report.');

        return Command::SUCCESS;
    }
}
