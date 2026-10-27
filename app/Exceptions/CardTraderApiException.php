<?php

namespace App\Exceptions;

use App\Models\Blueprint;
use Exception;
use Throwable;

/**
 * CardTrader could not provide the marketplace data (missing configuration,
 * network error, error response). Thrown instead of returning "no prices",
 * so that the failure is retried and stays visible (Horizon, failed_jobs).
 */
class CardTraderApiException extends Exception
{
    public static function notConfigured(): self
    {
        return new self('CardTrader API is not configured (CARDTRADER_API_TOKEN / CARDTRADER_API_BASE_URL).');
    }

    public static function requestFailed(Blueprint $blueprint, Throwable $previousException): self
    {
        return new self(
            "CardTrader marketplace request failed for blueprint {$blueprint->id} (CardTrader id {$blueprint->ct_id}): {$previousException->getMessage()}",
            0,
            $previousException
        );
    }
}
