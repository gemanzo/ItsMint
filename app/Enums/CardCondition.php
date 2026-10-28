<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/**
 * Canonical card conditions owned by users (user cards, add/sell forms).
 *
 * The backing values are the codes stored in user_cards.condition.
 *
 * Market prices (card_prices.condition) keep the condition names of CardTrader,
 * whose grading scale has the same five steps with different names:
 * marketConditionName() and fromProviderValue() translate between the two.
 */
enum CardCondition: string
{
    case NEAR_MINT = 'NM';
    case LIGHTLY_PLAYED = 'LP';
    case MODERATELY_PLAYED = 'MP';
    case HEAVILY_PLAYED = 'HP';
    case DAMAGED = 'DMG';

    /**
     * Spellings accepted in input (codes, our labels, CardTrader names)
     * mapped to the canonical condition. Keys are lowercase.
     */
    private const INPUT_ALIASES = [
        'nm' => self::NEAR_MINT,
        'near mint' => self::NEAR_MINT,

        'lp' => self::LIGHTLY_PLAYED,
        'lightly played' => self::LIGHTLY_PLAYED,
        'sp' => self::LIGHTLY_PLAYED,
        'slightly played' => self::LIGHTLY_PLAYED,

        'mp' => self::MODERATELY_PLAYED,
        'moderately played' => self::MODERATELY_PLAYED,

        'hp' => self::HEAVILY_PLAYED,
        'heavily played' => self::HEAVILY_PLAYED,
        'pl' => self::HEAVILY_PLAYED,
        'played' => self::HEAVILY_PLAYED,

        'dmg' => self::DAMAGED,
        'damaged' => self::DAMAGED,
        'po' => self::DAMAGED,
        'poor' => self::DAMAGED,
    ];

    /**
     * Normalize a condition written as a code, a label or a CardTrader name.
     *
     * Returns null for missing or unknown values.
     */
    public static function fromProviderValue(?string $rawCondition): ?self
    {
        if ($rawCondition === null) {
            return null;
        }

        $normalizedKey = strtolower(trim($rawCondition));

        return self::INPUT_ALIASES[$normalizedKey] ?? null;
    }

    /**
     * Canonical code of an already validated condition.
     *
     * @throws InvalidArgumentException when the condition is not recognized
     */
    public static function normalize(string $rawCondition): string
    {
        $condition = self::fromProviderValue($rawCondition);

        if ($condition === null) {
            throw new InvalidArgumentException("Unknown card condition: {$rawCondition}");
        }

        return $condition->value;
    }

    /**
     * Every accepted input spelling, keyed by its lowercase form, with its canonical code.
     *
     * @return array<string, string>
     */
    public static function inputAliases(): array
    {
        $aliasesToCodes = [];
        foreach (self::INPUT_ALIASES as $alias => $condition) {
            $aliasesToCodes[$alias] = $condition->value;
        }

        return $aliasesToCodes;
    }

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEAR_MINT => 'Near Mint',
            self::LIGHTLY_PLAYED => 'Lightly Played',
            self::MODERATELY_PLAYED => 'Moderately Played',
            self::HEAVILY_PLAYED => 'Heavily Played',
            self::DAMAGED => 'Damaged',
        };
    }

    /**
     * Short description of the condition, for the forms.
     */
    public function description(): string
    {
        return match ($this) {
            self::NEAR_MINT => 'Perfect condition',
            self::LIGHTLY_PLAYED => 'Minor wear',
            self::MODERATELY_PLAYED => 'Visible wear',
            self::HEAVILY_PLAYED => 'Major wear',
            self::DAMAGED => 'Significant damage',
        };
    }

    /**
     * Name of the same condition in market prices (CardTrader's scale), used to
     * look up the price of a user's card in card_prices.
     */
    public function marketConditionName(): string
    {
        return match ($this) {
            self::NEAR_MINT => 'Near Mint',
            self::LIGHTLY_PLAYED => 'Slightly Played',
            self::MODERATELY_PLAYED => 'Moderately Played',
            self::HEAVILY_PLAYED => 'Played',
            self::DAMAGED => 'Poor',
        };
    }

    /**
     * Share of the Near Mint price used to estimate a card in this condition
     * when no market price exists for the condition itself.
     */
    public function priceAdjustmentFactor(): float
    {
        return match ($this) {
            self::NEAR_MINT => 1.00,
            self::LIGHTLY_PLAYED => 0.90,
            self::MODERATELY_PLAYED => 0.75,
            self::HEAVILY_PLAYED => 0.50,
            self::DAMAGED => 0.25,
        };
    }
}
