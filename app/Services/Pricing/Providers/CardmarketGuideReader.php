<?php

declare(strict_types=1);

namespace App\Services\Pricing\Providers;

use App\Models\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CardmarketGuideReader
{
    public function __construct(
        private readonly ?string $downloadUrl = null,
    ) {}

    /**
     * Lookup normalized rows for a blueprint from Cardmarket guide JSON.
     * Returns an array of associative arrays with normalized fields.
     *
     * @return array<int,array<string,mixed>>
     */
    public function lookup(Blueprint $blueprint): array
    {
        $data = $this->fetchGuideJson();
        if ($data === null) {
            return [];
        }

        $rows = $data['priceGuides'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $createdAt = $data['createdAt'] ?? now()->toDateTimeString();
        $bpCmkIds = $this->normalizeCardMarketIds($blueprint->card_market_ids);

        if ($bpCmkIds->isEmpty()) {
            return [];
        }

        $lang = strtolower($blueprint->primary_language ?? 'en');

        $matches = [];
        foreach ($rows as $row) {
            $idProduct = isset($row['idProduct']) && is_numeric($row['idProduct']) ? (int) $row['idProduct'] : null;
            if ($idProduct === null) {
                continue;
            }
            if (!$bpCmkIds->contains($idProduct)) {
                continue;
            }

            $avg = $row['avg'] ?? null;
            $trend = $row['trend'] ?? null;

            $matches[] = [
                'avg_price_eur' => is_numeric($avg) ? (float) $avg : null,
                'trend_price_eur' => is_numeric($trend) ? (float) $trend : null,
                'date' => $createdAt,
                'language' => $lang,
                'condition' => 'Near Mint',
                'is_foil' => false,
                'sample_size' => null,
            ];
        }

        return $matches;
    }

    /**
     * Fetch and cache the Cardmarket price guide JSON.
     * Cache for 60 minutes to avoid repeated downloads.
     *
     * @return array<int,array<string,mixed>>|null
     */
    private function fetchGuideJson(): ?array
    {
        $cacheKey = 'cardmarket_price_guide_json_v1';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->downloadUrl ?? (config('services.cardmarket.op_price_guide_url') ?: env('CARDMARKET_OP_PRICE_GUIDE'));
        if (!$url) {
            Log::warning('CardmarketGuideReader: op_price_guide_url not configured.');
            return null;
        }

        try {
            $resp = Http::timeout(30)->get($url);
            if (!$resp->successful()) {
                Log::warning('CardmarketGuideReader: failed to download JSON', ['status' => $resp->status()]);
                return null;
            }
            $data = $resp->json();
            if (!is_array($data)) {
                return null;
            }
            Cache::put($cacheKey, $data, now()->addMinutes(60));
            return $data;
        } catch (\Throwable $e) {
            Log::error('CardmarketGuideReader exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalize possible shapes of card_market_ids into a flat collection of ints.
     */
    private function normalizeCardMarketIds(mixed $raw)
    {
        $ids = collect();

        if (is_null($raw)) {
            return $ids;
        }

        if (is_string($raw)) {
            // try JSON decode or comma separated
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }

        if (is_array($raw)) {
            // could be numeric array or associative map { lang => [ids] }
            foreach ($raw as $k => $v) {
                if (is_numeric($v)) {
                    $ids->push((int) $v);
                } elseif (is_array($v)) {
                    foreach ($v as $vv) {
                        if (is_numeric($vv)) {
                            $ids->push((int) $vv);
                        }
                    }
                }
            }
        }

        return $ids->unique()->values();
    }

    private function sameCollector(string $a, string $b): bool
    {
        return Str::of($a)->replace(['-', ' '], '')->lower() == Str::of($b)->replace(['-', ' '], '')->lower();
    }

    private function sameSet(string $a, string $b): bool
    {
        return Str::of($a)->squish()->lower()->startsWith(Str::of($b)->squish()->lower())
            || Str::of($b)->squish()->lower()->startsWith(Str::of($a)->squish()->lower());
    }
}
