<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validation rules shared by every endpoint that receives portfolio data
 * (quantities, prices, dates, notes), so that the limits are defined once.
 */
final class PortfolioInputRules
{
    /**
     * Largest quantity accepted in one operation.
     */
    public const MAX_QUANTITY = 10_000;

    /**
     * Largest unit price accepted: 1,000,000.00 EUR.
     */
    public const MAX_UNIT_PRICE_CENTS = 100_000_000;

    /**
     * Largest single cash movement (deposit, withdrawal): 10,000,000.00 EUR.
     */
    public const MAX_CASH_AMOUNT_CENTS = 1_000_000_000;

    public const MAX_NOTES_LENGTH = 1000;

    public const MAX_PER_PAGE = 100;

    public static function quantity(): string
    {
        return 'integer|min:1|max:'.self::MAX_QUANTITY;
    }

    public static function unitPriceCents(): string
    {
        return 'integer|min:0|max:'.self::MAX_UNIT_PRICE_CENTS;
    }

    public static function unitPriceEuros(): string
    {
        return 'numeric|min:0|max:'.(self::MAX_UNIT_PRICE_CENTS / 100);
    }

    public static function cashAmountCents(): string
    {
        return 'integer|min:1|max:'.self::MAX_CASH_AMOUNT_CENTS;
    }

    public static function notes(): string
    {
        return 'string|max:'.self::MAX_NOTES_LENGTH;
    }

    /**
     * A date that is not in the future.
     *
     * The application runs in UTC while the forms send the user's local date: in Italy
     * (ahead of UTC) today's local date is still "tomorrow" in UTC just after midnight,
     * so tomorrow is tolerated. Dates further in the future are refused.
     */
    public static function notFutureDate(): string
    {
        return 'date|before_or_equal:tomorrow';
    }

    /**
     * Requested page size clamped to [1, MAX_PER_PAGE].
     */
    public static function clampPerPage(mixed $requestedPerPage, int $defaultPerPage): int
    {
        if (! is_numeric($requestedPerPage)) {
            return $defaultPerPage;
        }

        return min(max((int) $requestedPerPage, 1), self::MAX_PER_PAGE);
    }
}
