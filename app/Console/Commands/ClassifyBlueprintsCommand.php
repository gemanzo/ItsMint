<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Services\BlueprintClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ClassifyBlueprintsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:classify-blueprints
                            {--test : Test classification without saving}
                            {--save : Save is_single_card flag to database}
                            {--limit=50 : Limit number of blueprints to process}
                            {--show-analysis : Show detailed analysis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify blueprints as single cards vs products using pattern analysis';

    /**
     * Execute the console command.
     */
    public function handle(BlueprintClassificationService $classificationService): int
    {
        $limit = (int) $this->option('limit');

        if ($this->option('test')) {
            return $this->testClassification($classificationService, $limit);
        }

        if ($this->option('save')) {
            return $this->saveClassifications($classificationService);
        }

        // Default: show classification summary
        return $this->showClassificationSummary($classificationService, $limit);
    }

    /**
     * Test classification on sample data.
     */
    private function testClassification(BlueprintClassificationService $classificationService, int $limit): int
    {
        $this->newLine();

        $results = $classificationService->bulkClassifyBlueprints($limit);

        $headers = ['Name', 'Card #', 'Classification', 'Score', 'Reasoning'];
        $rows = [];

        foreach ($results as $result) {
            $analysis = $result['analysis'];
            $reasoning = $this->buildReasoningString($analysis);

            $rows[] = [
                $this->truncate($result['name'], 30),
                $result['card_number'] ?? 'N/A',
                $result['classification'],
                number_format($analysis['analysis_score'], 2),
                $reasoning,
            ];
        }

        $this->table($headers, $rows);

        // Show statistics
        $this->showTestStatistics($results);

        return Command::SUCCESS;
    }

    /**
     * Show classification summary.
     */
    private function showClassificationSummary(BlueprintClassificationService $classificationService, int $limit): int
    {
        $this->newLine();

        $results = $classificationService->bulkClassifyBlueprints($limit);

        $stats = [
            'single_card' => 0,
            'likely_single_card' => 0,
            'product' => 0,
            'likely_product' => 0,
            'unknown' => 0,
        ];

        foreach ($results as $result) {
            $classification = $result['classification'];
            if (isset($stats[$classification])) {
                $stats[$classification]++;
            }
        }

        foreach ($stats as $type => $count) {
            $percentage = $limit > 0 ? round(($count / $limit) * 100, 1) : 0;
            $this->line("  {$type}: {$count} ({$percentage}%)");
        }

        $this->newLine();

        if ($this->option('show-analysis')) {
            $this->showDetailedAnalysis($results);
        }

        return Command::SUCCESS;
    }

    /**
     * Save classifications to database.
     */
    private function saveClassifications(BlueprintClassificationService $classificationService): int
    {
        // Check if columns exist
        if (!$this->columnExists('blueprints', 'product_type')) {
            $this->error('Column product_type does not exist. Please run the migration first.');
            return Command::FAILURE;
        }

        $total = Blueprint::count();

        $progressBar = $this->output->createProgressBar($total);
        $updated = 0;

        Blueprint::chunk(100, function ($blueprints) use ($classificationService, $progressBar, &$updated) {
            foreach ($blueprints as $blueprint) {
                $analysis = $classificationService->getClassificationAnalysis($blueprint);

                // Map classification to product type
                $productType = match ($analysis['classification']) {
                    'single_card' => 'single_card',
                    'likely_single_card' => 'single_card',
                    'product' => 'product',
                    'likely_product' => 'product',
                    default => 'unknown',
                };

                $blueprint->update([
                    'product_type' => $productType,
                    'ai_classification' => $analysis['classification'],
                    'ai_confidence_score' => $analysis['analysis_score'],
                ]);

                $updated++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Show final stats
        $singleCards = Blueprint::where('product_type', 'single_card')->count();
        $products = Blueprint::whereIn('product_type', ['product', 'unknown'])->count();
        $highConfidence = Blueprint::where('ai_confidence_score', '>=', 0.8)->count();

        $this->line("  Single cards: {$singleCards}");
        $this->line("  Products: {$products}");
        $this->line("  High confidence (≥0.8): {$highConfidence}");

        return Command::SUCCESS;
    }

    /**
     * Build reasoning string for analysis.
     */
    private function buildReasoningString(array $analysis): string
    {
        $reasons = [];

        if ($analysis['has_collector_number']) {
            $reasons[] = 'has_collector#';
        }

        if ($analysis['matches_non_card_patterns']) {
            $reasons[] = 'product_pattern';
        }

        if ($analysis['has_card_ability_pattern']) {
            $reasons[] = 'ability_pattern';
        }

        if ($analysis['looks_like_character_name']) {
            $reasons[] = 'character_name';
        }

        if ($analysis['word_count'] > 6) {
            $reasons[] = 'long_name';
        }

        return empty($reasons) ? 'no_clear_indicators' : implode(', ', $reasons);
    }

    /**
     * Show test statistics.
     */
    private function showTestStatistics(array $results): void
    {
        $this->newLine();

        $withCollectorNumber = 0;
        $productPatterns = 0;
        $abilityPatterns = 0;
        $characterNames = 0;

        foreach ($results as $result) {
            $analysis = $result['analysis'];

            if ($analysis['has_collector_number']) $withCollectorNumber++;
            if ($analysis['matches_non_card_patterns']) $productPatterns++;
            if ($analysis['has_card_ability_pattern']) $abilityPatterns++;
            if ($analysis['looks_like_character_name']) $characterNames++;
        }

        $total = count($results);

        $this->line("  With collector numbers: {$withCollectorNumber} (" . round(($withCollectorNumber / $total) * 100, 1) . "%)");
        $this->line("  Match product patterns: {$productPatterns} (" . round(($productPatterns / $total) * 100, 1) . "%)");
        $this->line("  Have ability patterns: {$abilityPatterns} (" . round(($abilityPatterns / $total) * 100, 1) . "%)");
        $this->line("  Look like character names: {$characterNames} (" . round(($characterNames / $total) * 100, 1) . "%)");
    }

    /**
     * Show detailed analysis breakdown.
     */
    private function showDetailedAnalysis(array $results): void
    {
        $this->newLine();
        $this->info('🔍 Detailed Analysis:');

        // Group by classification
        $grouped = [];
        foreach ($results as $result) {
            $classification = $result['classification'];
            if (!isset($grouped[$classification])) {
                $grouped[$classification] = [];
            }
            $grouped[$classification][] = $result;
        }

        foreach ($grouped as $classification => $items) {
            $this->newLine();
            $this->info("=== {$classification} ({" . count($items) . "}) ===");

            foreach (array_slice($items, 0, 5) as $item) { // Show max 5 examples
                $this->line("  {$item['name']} (Score: " . number_format($item['analysis']['analysis_score'], 2) . ")");
            }

            if (count($items) > 5) {
                $this->line("  ... and " . (count($items) - 5) . " more");
            }
        }
    }

    /**
     * Check if column exists.
     */
    private function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }



    /**
     * Truncate string for display.
     */
    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}
