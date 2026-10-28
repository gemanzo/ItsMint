<?php

declare(strict_types=1);

use App\Enums\CardCondition;
use App\Enums\CardLanguage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * User cards were stored with mixed vocabularies depending on the form used
 * ("NM" or "Near Mint", "EN" or "en"): the same card could end up in two
 * holdings and never match its market price. Existing values are normalized to
 * the canonical codes and CHECK constraints keep any other value out.
 */
return new class extends Migration
{
    /**
     * Canonical values at the time of this migration (CardCondition / CardLanguage).
     * Written out here so that the constraints don't change if the enums do.
     */
    private const CONDITION_CODES = ['NM', 'LP', 'MP', 'HP', 'DMG'];

    private const LANGUAGE_CODES = ['en', 'jp', 'fr', 'zh-cn', 'zh-tw', 'ko'];

    public function up(): void
    {
        $this->normalizeColumn('condition', fn (string $value) => CardCondition::fromProviderValue($value)?->value);
        $this->normalizeColumn('language', fn (string $value) => CardLanguage::fromProviderValue($value)?->value);

        DB::statement(sprintf(
            'ALTER TABLE user_cards ADD CONSTRAINT user_cards_condition_check CHECK (condition IN (%s))',
            $this->sqlList(self::CONDITION_CODES)
        ));
        DB::statement(sprintf(
            'ALTER TABLE user_cards ADD CONSTRAINT user_cards_language_check CHECK (language IN (%s))',
            $this->sqlList(self::LANGUAGE_CODES)
        ));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_cards DROP CONSTRAINT IF EXISTS user_cards_language_check');
        DB::statement('ALTER TABLE user_cards DROP CONSTRAINT IF EXISTS user_cards_condition_check');
        // Normalized values are kept: they are valid in the previous schema too
    }

    /**
     * Rewrite every distinct value of the column with its canonical form.
     *
     * Stops the migration when a value can't be mapped: guessing would silently
     * change user data.
     *
     * @param  Closure(string): ?string  $toCanonicalValue
     */
    private function normalizeColumn(string $column, Closure $toCanonicalValue): void
    {
        $unmappableValues = [];

        foreach (DB::table('user_cards')->distinct()->pluck($column) as $currentValue) {
            $canonicalValue = $toCanonicalValue((string) $currentValue);

            if ($canonicalValue === null) {
                $unmappableValues[] = $currentValue;

                continue;
            }

            if ($canonicalValue !== $currentValue) {
                DB::table('user_cards')->where($column, $currentValue)->update([$column => $canonicalValue]);
            }
        }

        if (! empty($unmappableValues)) {
            throw new RuntimeException(sprintf(
                'user_cards.%s contains values that are not a known card %s: %s. Fix them by hand, then run the migration again.',
                $column,
                $column,
                implode(', ', $unmappableValues)
            ));
        }
    }

    /**
     * @param  string[]  $values
     */
    private function sqlList(array $values): string
    {
        return implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
    }
};
