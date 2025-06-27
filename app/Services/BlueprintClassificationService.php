<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use Illuminate\Support\Str;

final class BlueprintClassificationService
{
    /**
     * Product type patterns that indicate non-card products.
     */
    private array $nonCardPatterns = [
        // Packs and Boxes
        'pack',
        'vol.',
        'vol ',
        'volume',
        'box',
        'booster',
        'display',
        'case',

        // Decks and Sets
        'deck',
        'starter deck',
        'set',
        'anniversary set',
        'championship set',

        // Accessories
        'sleeves',
        'playmat',
        'mat',
        'binder',
        'storage',

        // Events and Promos (when they are product names, not card names)
        'participation pack',
        'winner pack',
        'championship pack',
        'regional pack',
        'tournament pack',
        'event pack',

        // Special Products
        'don!! card pack',
        'special don!!',
        'promo pack',
        'promotion pack',
        'film red uta promo pack',
    ];

    /**
     * Patterns that strongly indicate single cards.
     */
    private array $singleCardIndicators = [
        // Card effects/abilities (quoted text usually means card abilities)
        '...', // Card abilities often have ellipsis
        '!!', // Exclamation in card names

        // Character names pattern (single character names)
        // These will be checked contextually
    ];

    /**
     * Words that when appearing alone or with simple modifiers indicate character cards.
     */
    private array $characterNamePatterns = [
        // Will be validated contextually - single names without product keywords
    ];

    /**
     * Classify a Blueprint as single card or product.
     */
    public function classifyBlueprint(Blueprint $blueprint): string
    {
        $name = $blueprint->name;

        if (empty($name)) {
            return 'unknown';
        }

        // Strong indicators for single cards
        if ($this->isDefinitelySingleCard($blueprint)) {
            return 'single_card';
        }

        // Strong indicators for non-card products
        if ($this->isDefinitelyProduct($blueprint)) {
            return 'product';
        }

        // Contextual analysis
        if ($this->isLikelySingleCard($blueprint)) {
            return 'likely_single_card';
        }

        if ($this->isLikelyProduct($blueprint)) {
            return 'likely_product';
        }

        return 'unknown';
    }

    /**
     * Check if Blueprint is definitely a single card.
     */
    private function isDefinitelySingleCard(Blueprint $blueprint): bool
    {
        // Has collector number (ST01-001, OP01-025, etc.)
        if ($this->hasValidCollectorNumber($blueprint)) {
            return true;
        }

        // Card ability patterns
        if ($this->hasCardAbilityPattern($blueprint->name)) {
            return true;
        }

        return false;
    }

    /**
     * Check if Blueprint is definitely a product.
     */
    private function isDefinitelyProduct(Blueprint $blueprint): bool
    {
        $name = strtolower($blueprint->name);

        foreach ($this->nonCardPatterns as $pattern) {
            if (Str::contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Blueprint is likely a single card.
     */
    private function isLikelySingleCard(Blueprint $blueprint): bool
    {
        $name = $blueprint->name;

        // Simple character name pattern (2-4 words, no product keywords)
        if ($this->looksLikeCharacterName($name)) {
            return true;
        }

        // Has card-like naming pattern
        if ($this->hasCardNamingPattern($name)) {
            return true;
        }

        return false;
    }

    /**
     * Check if Blueprint is likely a product.
     */
    private function isLikelyProduct(Blueprint $blueprint): bool
    {
        $name = $blueprint->name;

        // Long descriptive names often products
        if (str_word_count($name) > 6) {
            return true;
        }

        // Contains year (often indicates special products)
        if (preg_match('/\b20\d{2}\b/', $name)) {
            return true;
        }

        // Contains "Official" (usually accessories)
        if (Str::contains(strtolower($name), 'official')) {
            return true;
        }

        return false;
    }

    /**
     * Check if has valid collector number.
     */
    private function hasValidCollectorNumber(Blueprint $blueprint): bool
    {
        $collectorNumber = $blueprint->properties['fixed_properties']['collector_number'] ?? null;

        if (!$collectorNumber) {
            return false;
        }

        // Valid collector number pattern: ST01-001, OP01-025, P-001, etc.
        return preg_match('/^[A-Z]+\d*-\d+/', $collectorNumber) === 1;
    }

    /**
     * Check if name has card ability pattern.
     */
    private function hasCardAbilityPattern(string $name): bool
    {
        // Card abilities often have specific patterns
        $abilityPatterns = [
            '/\.{3}/', // Ellipsis (...)
            '/^".*"$/', // Quoted text
            '/!!$/', // Ends with !!
            '/\bI want to\b/i', // Card ability text
            '/\bNothing.*at All\b/i', // Specific card ability
        ];

        foreach ($abilityPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if looks like character name.
     */
    private function looksLikeCharacterName(string $name): bool
    {
        // Remove quotes and clean the name
        $cleanName = trim($name, '"');
        $wordCount = str_word_count($cleanName);

        // Single character names are usually 1-4 words
        if ($wordCount >= 1 && $wordCount <= 4) {
            // Check it doesn't contain product keywords
            $lowerName = strtolower($cleanName);

            $productKeywords = ['pack', 'box', 'sleeves', 'playmat', 'set', 'deck'];
            foreach ($productKeywords as $keyword) {
                if (Str::contains($lowerName, $keyword)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Check if has card naming pattern.
     */
    private function hasCardNamingPattern(string $name): bool
    {
        // Cards often have specific naming patterns
        $patterns = [
            '/^[A-Z][a-z]+\.[A-Z]\./', // Monkey.D.Luffy pattern
            '/^Ms\. /', // Ms. All Sunday pattern
            '/^Dr\. /', // Dr. pattern
            '/^Charlotte /', // Charlotte family names
            '/^Vinsmoke /', // Vinsmoke family names
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get detailed classification analysis.
     */
    public function getClassificationAnalysis(Blueprint $blueprint): array
    {
        $classification = $this->classifyBlueprint($blueprint);

        return [
            'classification' => $classification,
            'has_collector_number' => $this->hasValidCollectorNumber($blueprint),
            'collector_number' => $blueprint->collector_number,
            'matches_non_card_patterns' => $this->isDefinitelyProduct($blueprint),
            'has_card_ability_pattern' => $this->hasCardAbilityPattern($blueprint->name),
            'looks_like_character_name' => $this->looksLikeCharacterName($blueprint->name),
            'word_count' => str_word_count($blueprint->name),
            'analysis_score' => $this->getConfidenceScore($blueprint),
        ];
    }

    /**
     * Get confidence score for classification.
     */
    private function getConfidenceScore(Blueprint $blueprint): float
    {
        $score = 0.0;

        if ($this->hasValidCollectorNumber($blueprint)) {
            $score += 0.9; // Very high confidence
        }

        if ($this->isDefinitelyProduct($blueprint)) {
            $score = -0.9; // Very high confidence it's NOT a card
        }

        if ($this->hasCardAbilityPattern($blueprint->name)) {
            $score += 0.7;
        }

        if ($this->looksLikeCharacterName($blueprint->name)) {
            $score += 0.5;
        }

        return max(-1.0, min(1.0, $score)); // Clamp between -1 and 1
    }

    /**
     * Bulk classify blueprints.
     */
    public function bulkClassifyBlueprints(int $limit = null): array
    {
        $query = Blueprint::query();

        if ($limit) {
            $query->limit($limit);
        }

        $blueprints = $query->get();
        $results = [];

        foreach ($blueprints as $blueprint) {
            $results[] = [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'card_number' => $blueprint->collector_number,
                'classification' => $this->classifyBlueprint($blueprint),
                'analysis' => $this->getClassificationAnalysis($blueprint),
            ];
        }

        return $results;
    }
}
