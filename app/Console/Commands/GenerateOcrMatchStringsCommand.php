<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GenerateOcrMatchStringsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:generate-blueprint-ocr-match-string
                          {blueprint_id? : ID of specific blueprint to process}
                          {--chunk=100 : Number of blueprints to process in each chunk}
                          {--force : Force regeneration even if match string already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OCR match strings for all blueprints or a specific blueprint based on their OneLog card data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $blueprintId = $this->argument('blueprint_id');

        if ($blueprintId) {
            return $this->processSingleBlueprint((int) $blueprintId);
        }

        return $this->processAllBlueprints();
    }

    /**
     * Process a single blueprint
     */
    private function processSingleBlueprint(int $blueprintId): int
    {
        $force = (bool) $this->option('force');

        $blueprint = Blueprint::with(['oneLogCards'])->find($blueprintId);

        if (!$blueprint) {
            $this->error("Blueprint with ID {$blueprintId} not found.");
            return Command::FAILURE;
        }

        if (!$force && $blueprint->ocr_match_string !== null) {
            $this->info("Blueprint {$blueprintId} already has OCR match string. Use --force to regenerate.");
            return Command::SUCCESS;
        }

        try {
            $matchString = $this->buildOcrMatchString($blueprint);

            if ($matchString) {
                $blueprint->update(['ocr_match_string' => $matchString]);
                $this->info("OCR match string generated for blueprint {$blueprintId}");
                return Command::SUCCESS;
            } else {
                $this->warn("No OneLog card data found for blueprint {$blueprintId}");
                return Command::SUCCESS;
            }
        } catch (\Exception $e) {
            $this->error("Error processing blueprint {$blueprintId}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Process all blueprints
     */
    private function processAllBlueprints(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $force = (bool) $this->option('force');

        $this->info('Starting OCR match string generation for all blueprints...');

        $query = Blueprint::with(['oneLogCards']);

        if (!$force) {
            $query->whereNull('ocr_match_string');
        }

        $totalBlueprints = $query->count();

        if ($totalBlueprints === 0) {
            $this->info('No blueprints to process.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$totalBlueprints} blueprints in chunks of {$chunkSize}...");

        $progressBar = $this->output->createProgressBar($totalBlueprints);
        $progressBar->start();

        $processed = 0;
        $updated = 0;
        $skipped = 0;

        $query->chunk($chunkSize, function ($blueprints) use (&$processed, &$updated, &$skipped, $progressBar) {
            foreach ($blueprints as $blueprint) {
                $processed++;

                try {
                    $matchString = $this->buildOcrMatchString($blueprint);

                    if ($matchString) {
                        $blueprint->update(['ocr_match_string' => $matchString]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error processing blueprint {$blueprint->id}: " . $e->getMessage());
                    $skipped++;
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info("Processing completed!");
        $this->info("Total processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");

        return Command::SUCCESS;
    }

    /**
     * Build OCR match string for a blueprint
     */
    private function buildOcrMatchString(Blueprint $blueprint): ?string
    {
        $primaryOneLogCard = $blueprint->getPrimaryOneLogCardAttribute();

        if (!$primaryOneLogCard) {
            return null;
        }

        $parts = [
            $blueprint->name ?? '',
            $primaryOneLogCard->serial ?? '',
            $primaryOneLogCard->type ?? '',
            $primaryOneLogCard->attribute ?? '',
            (string)($primaryOneLogCard->power ?? ''),
            $this->formatColors($primaryOneLogCard->colors),
            $primaryOneLogCard->feature ?? '',
            $primaryOneLogCard->effect ?? '',
        ];

        $concatenated = implode(' ', array_filter($parts));

        return $this->normalizeText($concatenated);
    }

    /**
     * Format colors array into a single string
     */
    private function formatColors(?array $colors): string
    {
        if (!$colors || !is_array($colors)) {
            return '';
        }

        return strtolower(implode('', $colors));
    }

    /**
     * Normalize text for OCR matching
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove special characters except spaces
        $text = preg_replace('/[^\w\s]/u', ' ', $text);

        // Normalize multiple spaces to single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim whitespace
        $text = trim($text);

        return $text;
    }
}
