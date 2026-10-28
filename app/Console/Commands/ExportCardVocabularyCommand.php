<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CardCondition;
use App\Enums\CardLanguage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Export the card vocabulary (CardCondition, CardLanguage) as TypeScript constants,
 * so that the frontend never keeps its own copy of the conditions and languages.
 *
 * Run it after changing one of the enums. CI runs it with --check and fails when the
 * committed file is out of date.
 */
final class ExportCardVocabularyCommand extends Command
{
    protected $signature = 'itsmint:export-card-vocabulary
                            {--check : Only verify that the generated file is up to date}';

    protected $description = 'Generate the TypeScript constants of card conditions and languages from the PHP enums';

    public const OUTPUT_RELATIVE_PATH = 'resources/js/constants/generated/cardVocabulary.ts';

    public function handle(): int
    {
        $outputPath = base_path(self::OUTPUT_RELATIVE_PATH);
        $generatedTypeScript = $this->renderTypeScript();

        if ($this->option('check')) {
            $isUpToDate = File::exists($outputPath) && File::get($outputPath) === $generatedTypeScript;

            if (! $isUpToDate) {
                $this->error(self::OUTPUT_RELATIVE_PATH.' is out of date: run php artisan itsmint:export-card-vocabulary');

                return self::FAILURE;
            }

            $this->info(self::OUTPUT_RELATIVE_PATH.' is up to date.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $generatedTypeScript);
        $this->info('Generated '.self::OUTPUT_RELATIVE_PATH);

        return self::SUCCESS;
    }

    private function renderTypeScript(): string
    {
        $conditionDefinitions = array_map(fn (CardCondition $condition) => [
            'value' => $condition->value,
            'label' => $condition->label(),
            'description' => $condition->description(),
            'marketConditionName' => $condition->marketConditionName(),
            'priceAdjustment' => $condition->priceAdjustmentFactor(),
        ], CardCondition::cases());

        $languageDefinitions = array_map(fn (CardLanguage $language) => [
            'code' => $language->value,
            'name' => $language->displayName(),
            'shortName' => $language->shortName(),
            'countryCode' => $language->flagCountryCode(),
        ], CardLanguage::cases());

        $typeScriptLines = [
            '// GENERATED FILE - DO NOT EDIT.',
            '// Source: App\\Enums\\CardCondition and App\\Enums\\CardLanguage.',
            '// Regenerate with: php artisan itsmint:export-card-vocabulary',
            '',
            '// Canonical card conditions (codes stored for user cards)',
            'export const CARD_CONDITION_DEFINITIONS = '.$this->toJson($conditionDefinitions).' as const;',
            '',
            '// Accepted spellings (lowercase) mapped to the canonical condition code',
            'export const CARD_CONDITION_ALIASES: Record<string, string> = '.$this->toJson(CardCondition::inputAliases()).';',
            '',
            '// Supported card languages',
            'export const CARD_LANGUAGE_DEFINITIONS = '.$this->toJson($languageDefinitions).' as const;',
            '',
        ];

        return implode("\n", $typeScriptLines);
    }

    private function toJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }
}
