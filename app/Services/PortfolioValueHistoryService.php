<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CardCondition;
use App\Models\Portfolio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PortfolioValueHistoryService
{
    /**
     * Calculate portfolio value history for a given period and metric.
     * Results are cached for 15 minutes to improve performance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateHistory(
        Portfolio $portfolio,
        string $period = '1M',
        string $metric = 'market_value'
    ): array {
        $cacheKey = "portfolio_value_history_{$portfolio->id}_{$period}_{$metric}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($portfolio, $period, $metric) {
            $endDate = now();
            $startDate = $this->calculateStartDate($period, $endDate, $portfolio->created_at);

            // Get user cards with minimal data needed.
            // Columns must be qualified: the pivot table also has an "id" column.
            $userCards = $portfolio->userCards()
                ->with(['blueprint:id'])
                ->get([
                    'user_cards.id',
                    'user_cards.blueprint_id',
                    'user_cards.condition',
                    'user_cards.language',
                    'user_cards.purchase_price_cents',
                    'user_cards.acquired_at',
                ]);

            if ($userCards->isEmpty()) {
                return [];
            }

            // Calculate data points based on period (limit to reasonable number)
            $dataPoints = $this->generateDataPoints($startDate, $endDate, $period);

            // Limit data points to prevent excessive calculations
            if (count($dataPoints) > 200) {
                $dataPoints = array_slice($dataPoints, 0, 200);
            }

            $valueHistory = [];

            // Pre-fetch prices for all blueprints in date range using optimized query
            $blueprintIds = $userCards->pluck('blueprint_id')->unique()->toArray();
            $pricesByBlueprint = $this->fetchPricesForBlueprints($blueprintIds, $startDate, $endDate);

            foreach ($dataPoints as $date) {
                $values = $this->calculateValuesForDateOptimized($userCards, $portfolio, $date, $pricesByBlueprint);
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
        });
    }

    /**
     * Clear portfolio value history cache for a specific portfolio.
     */
    public function clearCache(Portfolio $portfolio): void
    {
        $periods = ['1D', '1W', '1M', '3M', '1Y', 'MAX'];
        $metrics = ['market_value', 'cost_basis', 'performance'];

        foreach ($periods as $period) {
            foreach ($metrics as $metric) {
                Cache::forget("portfolio_value_history_{$portfolio->id}_{$period}_{$metric}");
            }
        }
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
     * Fetch prices for multiple blueprints optimized with single query.
     *
     * @return array<int, Collection>
     */
    private function fetchPricesForBlueprints(array $blueprintIds, Carbon $startDate, Carbon $endDate): array
    {
        if (empty($blueprintIds)) {
            return [];
        }

        // Use raw query to efficiently fetch prices grouped by blueprint
        $prices = DB::table('card_prices')
            ->whereIn('blueprint_id', $blueprintIds)
            ->whereBetween('fetched_at', [$startDate, $endDate])
            ->select('id', 'blueprint_id', 'price_cents', 'language', 'condition', 'fetched_at')
            ->orderBy('blueprint_id')
            ->orderBy('fetched_at', 'desc')
            ->get()
            ->groupBy('blueprint_id');

        // Keep each blueprint's prices as a Collection (toArray() would turn them
        // into plain arrays, breaking the Collection methods used by the callers)
        return $prices->all();
    }

    /**
     * Calculate market value and cost basis for a specific date (optimized version).
     *
     * @return array<string, int|float>
     */
    private function calculateValuesForDateOptimized($userCards, Portfolio $portfolio, Carbon $date, array $pricesByBlueprint): array
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

            // Find the closest market price to this date using pre-fetched prices
            $priceCents = $this->findPriceForDateOptimized($userCard, $date, $quantity, $pricesByBlueprint);
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
     * Find the appropriate price for a user card at a specific date (optimized version).
     */
    private function findPriceForDateOptimized($userCard, Carbon $date, int $quantity, array $pricesByBlueprint): int
    {
        $blueprintId = $userCard->blueprint_id;
        $prices = $pricesByBlueprint[$blueprintId] ?? collect();

        if ($prices->isEmpty()) {
            // Fallback: Use estimated value from UserCard accessor
            return ($userCard->estimated_value_cents ?? 0) * $quantity;
        }

        // Market prices use CardTrader's condition names (e.g. LP = "Slightly Played"):
        // translate the user card's condition code before comparing
        $marketConditionName = CardCondition::fromProviderValue($userCard->condition)?->marketConditionName();

        // Find the closest price to this date matching language and condition
        $closestPrice = $prices
            ->filter(function ($price) use ($userCard, $date, $marketConditionName) {
                return strtolower($price->language ?? '') === strtolower($userCard->language ?? '') &&
                    $price->condition === $marketConditionName &&
                    Carbon::parse($price->fetched_at)->lte($date);
            })
            ->first();

        if ($closestPrice) {
            return $closestPrice->price_cents * $quantity;
        }

        // Fallback: Use the most recent price available for this card (regardless of date)
        $fallbackPrice = $prices
            ->filter(function ($price) use ($userCard, $marketConditionName) {
                return strtolower($price->language ?? '') === strtolower($userCard->language ?? '') &&
                    $price->condition === $marketConditionName;
            })
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
