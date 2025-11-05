<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use Carbon\Carbon;

final class PortfolioValueHistoryService
{
    /**
     * Calculate portfolio value history for a given period and metric.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateHistory(
        Portfolio $portfolio,
        string $period = '1M',
        string $metric = 'market_value'
    ): array {
        $endDate = now();
        $startDate = $this->calculateStartDate($period, $endDate, $portfolio->created_at);

        // Get all user cards in this portfolio with their relationships
        $userCards = $portfolio->userCards()
            ->with(['blueprint.cardPrices' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('fetched_at', [$startDate, $endDate])
                    ->orderBy('fetched_at');
            }])
            ->get();

        if ($userCards->isEmpty()) {
            return [];
        }

        // Calculate data points based on period
        $dataPoints = $this->generateDataPoints($startDate, $endDate, $period);
        $valueHistory = [];

        foreach ($dataPoints as $date) {
            $values = $this->calculateValuesForDate($userCards, $portfolio, $date);
            $value = $this->calculateMetricValue($values, $metric);

            $valueHistory[] = [
                'date' => $date->toDateString(),
                'fetched_at' => $date->toISOString(),
                'value_cents' => $value,
                'market_value_cents' => $values['market_value'],
                'cost_basis_cents' => $values['cost_basis'],
                'performance_percentage' => $values['performance'],
            ];
        }

        return $valueHistory;
    }

    /**
     * Calculate start date based on period.
     */
    private function calculateStartDate(string $period, Carbon $endDate, ?Carbon $portfolioCreatedAt): Carbon
    {
        return match ($period) {
            '1D' => $endDate->copy()->subDay(),
            '1W' => $endDate->copy()->subWeek(),
            '1M' => $endDate->copy()->subMonth(),
            '3M' => $endDate->copy()->subMonths(3),
            '1Y' => $endDate->copy()->subYear(),
            'MAX' => $portfolioCreatedAt ?: $endDate->copy()->subYears(2),
            default => $endDate->copy()->subMonth(),
        };
    }

    /**
     * Calculate market value and cost basis for a specific date.
     *
     * @return array<string, int|float>
     */
    private function calculateValuesForDate($userCards, Portfolio $portfolio, Carbon $date): array
    {
        $totalMarketValue = 0;
        $totalCostBasis = 0;

        foreach ($userCards as $userCard) {
            // Only include cards that were acquired before this date
            $acquiredAt = $userCard->acquired_at ? Carbon::parse($userCard->acquired_at) : $portfolio->created_at;
            if ($acquiredAt && $acquiredAt->gt($date)) {
                continue;
            }

            $quantity = $userCard->pivot->quantity ?? 1;

            // Add to cost basis (purchase price * quantity)
            $totalCostBasis += ($userCard->purchase_price_cents ?? 0) * $quantity;

            // Find the closest market price to this date
            $priceCents = $this->findPriceForDate($userCard, $date, $quantity);
            $totalMarketValue += $priceCents;
        }

        $performance = $totalCostBasis > 0
            ? (($totalMarketValue - $totalCostBasis) / $totalCostBasis) * 100
            : 0;

        return [
            'market_value' => $totalMarketValue,
            'cost_basis' => $totalCostBasis,
            'performance' => $performance,
        ];
    }

    /**
     * Find the appropriate price for a user card at a specific date.
     */
    private function findPriceForDate($userCard, Carbon $date, int $quantity): int
    {
        // Find the closest market price to this date
        $closestPrice = $userCard->blueprint->cardPrices
            ->filter(function ($price) use ($userCard, $date) {
                // Match language and condition
                return $price->language === $userCard->language &&
                    $price->condition === $userCard->condition &&
                    Carbon::parse($price->fetched_at)->lte($date);
            })
            ->sortByDesc('fetched_at')
            ->first();

        if ($closestPrice) {
            return $closestPrice->price_cents * $quantity;
        }

        // Fallback: Use the most recent price available for this card (regardless of date)
        $fallbackPrice = $userCard->blueprint->cardPrices
            ->filter(function ($price) use ($userCard) {
                return $price->language === $userCard->language &&
                    $price->condition === $userCard->condition;
            })
            ->sortByDesc('fetched_at')
            ->first();

        if ($fallbackPrice) {
            return $fallbackPrice->price_cents * $quantity;
        }

        // Ultimate fallback: Use the estimated value from the UserCard
        return ($userCard->estimated_value_cents ?? 0) * $quantity;
    }

    /**
     * Calculate the metric value based on requested metric type.
     */
    private function calculateMetricValue(array $values, string $metric): int|float
    {
        return match ($metric) {
            'market_value' => $values['market_value'],
            'cost_basis' => $values['cost_basis'],
            'performance' => $values['performance'],
            default => $values['market_value'],
        };
    }

    /**
     * Generate appropriate data points for the given period.
     *
     * @return array<int, Carbon>
     */
    private function generateDataPoints(Carbon $startDate, Carbon $endDate, string $period): array
    {
        $points = [];
        $current = $startDate->copy();

        // Determine interval and step based on period
        [$interval, $step] = $this->getIntervalAndStep($period);

        while ($current->lte($endDate)) {
            $points[] = $current->copy();

            match ($interval) {
                'hour' => $current->addHours($step),
                'day' => $current->addDays($step),
                'week' => $current->addWeeks($step),
                default => $current->addDays($step),
            };

            // Prevent infinite loops
            if (count($points) > 1000) {
                break;
            }
        }

        // Always include the end date
        if (! $points || $points[count($points) - 1]->lt($endDate)) {
            $points[] = $endDate->copy();
        }

        return $points;
    }

    /**
     * Get interval and step for data point generation.
     *
     * @return array{0: string, 1: int}
     */
    private function getIntervalAndStep(string $period): array
    {
        $interval = match ($period) {
            '1D' => 'hour',
            '1W', '1M', '3M' => 'day',
            '1Y', 'MAX' => 'week',
            default => 'day',
        };

        $step = match ($period) {
            '1D' => 2, // Every 2 hours
            '1W' => 1, // Daily
            '1M' => 2, // Every 2 days
            '3M' => 3, // Every 3 days
            '1Y' => 1, // Weekly
            'MAX' => 2, // Every 2 weeks
            default => 1,
        };

        return [$interval, $step];
    }
}
