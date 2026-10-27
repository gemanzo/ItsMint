<?php

declare(strict_types=1);

namespace App\Services\Pricing\Providers;

use App\Enums\CardLanguage;
use App\Models\Blueprint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CardmarketGuideReader
{
    private ?array $data = null;

    private array $byIdProduct = [];

    private bool $isIndexed = false;

    public function __construct(
        private readonly ?string $downloadUrl = null,
    ) {}

    /**
     * Preload and index the JSON once per process (command run).
     */
    public function preload(): void
    {
        $data = $this->fetchGuideJson();
        if (! is_array($data)) {
            return;
        }
        $rows = $data['priceGuides'] ?? [];
        if (! is_array($rows)) {
            return;
        }
        if (! $this->isIndexed) {
            foreach ($rows as $row) {
                if (isset($row['idProduct']) && is_numeric($row['idProduct'])) {
                    $this->byIdProduct[(int) $row['idProduct']] = $row;
                }
            }
            $this->isIndexed = true;
        }
    }

    /**
     * When Cardmarket generated the loaded price guide, or null when the guide is
     * missing or doesn't say it. Used to refuse a stale guide: its prices would
     * otherwise be stored as current.
     */
    public function guideCreatedAt(): ?CarbonImmutable
    {
        $guideData = $this->fetchGuideJson();
        $createdAt = $guideData['createdAt'] ?? null;

        if (! is_string($createdAt) || $createdAt === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($createdAt);
        } catch (\Throwable) {
            return null;
        }
    }

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
        if (! is_array($rows)) {
            return [];
        }

        $createdAt = $data['createdAt'] ?? now()->toDateTimeString();
        // Canonical code (e.g. CardTrader's "ja" becomes "jp"), English when unknown
        $lang = (CardLanguage::fromProviderValue($blueprint->primary_language) ?? CardLanguage::ENGLISH)->value;

        // Build fast index once per process
        if (! $this->isIndexed) {
            foreach ($rows as $row) {
                if (isset($row['idProduct']) && is_numeric($row['idProduct'])) {
                    $this->byIdProduct[(int) $row['idProduct']] = $row;
                }
            }
            $this->isIndexed = true;
        }

        // card_market_ids is a flat array; select by configured position index or preferred id override
        $flatIds = $this->normalizeCardMarketIds($blueprint->card_market_ids)->all();
        if (empty($flatIds)) {
            return [];
        }

        // Configurable index-by-language rule (e.g., en:0, ja:1)
        $idx = $this->preferredIndexForLanguage($lang);
        if ($idx !== null && isset($flatIds[$idx])) {
            $idsForLang = [(int) $flatIds[$idx]];
            if (count($flatIds) > 1) {
                Log::info('CardmarketGuideReader: selected id by language index rule', [
                    'blueprint_id' => $blueprint->id,
                    'language' => $lang,
                    'index' => $idx,
                    'selected_id' => (int) $flatIds[$idx],
                    'all_ids' => array_map('intval', $flatIds),
                ]);
            }
        } else {
            // Fallback: consider all and choose by price
            $idsForLang = array_values(array_unique(array_map('intval', $flatIds)));
        }

        // Build candidate rows from selected IDs
        $candidateRows = [];
        foreach ($idsForLang as $id) {
            $row = $this->byIdProduct[(int) $id] ?? null;
            if ($row) {
                $candidateRows[] = $row;
            }
        }

        if (empty($candidateRows)) {
            return [];
        }

        // If multiple candidates for this language, choose one by price heuristic
        $chosen = count($candidateRows) > 1
            ? $this->selectBestRowByPrice($idsForLang, $candidateRows)
            : $candidateRows[0];

        if (count($candidateRows) > 1) {
            $cands = array_map(fn ($r) => [
                'idProduct' => $r['idProduct'] ?? null,
                'avg' => $r['avg'] ?? null,
                'trend' => $r['trend'] ?? null,
            ], $candidateRows);
            Log::info('CardmarketGuideReader: language-filtered multiple IDs, selected by price', [
                'blueprint_id' => $blueprint->id,
                'language' => $lang,
                'ids_considered' => $idsForLang,
                'selected' => [
                    'idProduct' => $chosen['idProduct'] ?? null,
                    'avg' => $chosen['avg'] ?? null,
                    'trend' => $chosen['trend'] ?? null,
                ],
                'candidates' => $cands,
            ]);
        }

        $avg = $chosen['avg'] ?? null;
        $trend = $chosen['trend'] ?? null;

        return [[
            'avg_price_eur' => is_numeric($avg) ? (float) $avg : null,
            'trend_price_eur' => is_numeric($trend) ? (float) $trend : null,
            'date' => $createdAt,
            'language' => $lang,
            'condition' => 'Near Mint',
            'is_foil' => false,
            'sample_size' => null,
        ]];
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
        if ($this->data !== null) {
            return $this->data;
        }
        // 1) Try local file first
        $local = config('services.cardmarket.op_price_guide_path');
        if (is_string($local) && is_file($local)) {
            try {
                $raw = file_get_contents($local);
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $this->data = $json;

                    return $this->data;
                }
            } catch (\Throwable) {
                // fallback to cache/http
            }
        }
        // 2) App cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->data = $cached;

            return $this->data;
        }

        // Read from config only: env() returns null once the configuration is cached
        $url = $this->downloadUrl ?? config('services.cardmarket.op_price_guide_url');
        if (! $url) {
            Log::warning('CardmarketGuideReader: op_price_guide_url not configured.');

            return null;
        }

        try {
            $resp = Http::timeout(30)->get($url);
            if (! $resp->successful()) {
                Log::warning('CardmarketGuideReader: failed to download JSON', ['status' => $resp->status()]);

                return null;
            }
            $data = $resp->json();
            if (! is_array($data)) {
                return null;
            }
            $this->data = $data;
            Cache::put($cacheKey, $data, now()->addHours(24));

            return $this->data;
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

    // Note: ID selection is language-aware via position, configured per language

    /**
     * Choose best row by trend/avg among candidates; tie-break using original order.
     *
     * @param  array<int,int>  $originalOrderIds
     * @param  array<int,array<string,mixed>>  $candidateRows
     */
    private function selectBestRowByPrice(array $originalOrderIds, array $candidateRows): ?array
    {
        $scored = array_map(function ($r) use ($originalOrderIds) {
            $price = 0.0;
            if (isset($r['trend']) && is_numeric($r['trend'])) {
                $price = (float) $r['trend'];
            } elseif (isset($r['avg']) && is_numeric($r['avg'])) {
                $price = (float) $r['avg'];
            }
            $id = isset($r['idProduct']) ? (int) $r['idProduct'] : 0;
            $orderRank = 999999;
            $idx = array_search($id, $originalOrderIds, true);
            if ($idx !== false) {
                $orderRank = (int) $idx;
            }

            return [
                'row' => $r,
                'price' => $price,
                'hasTrend' => isset($r['trend']) && is_numeric($r['trend']),
                'hasAvg' => isset($r['avg']) && is_numeric($r['avg']),
                'orderRank' => $orderRank,
                'idProduct' => $id,
            ];
        }, $candidateRows);

        usort($scored, function ($a, $b) {
            if ($a['price'] !== $b['price']) {
                return $a['price'] < $b['price'] ? 1 : -1;
            }
            if ($a['hasTrend'] !== $b['hasTrend']) {
                return $a['hasTrend'] ? -1 : 1;
            }
            if ($a['hasAvg'] !== $b['hasAvg']) {
                return $a['hasAvg'] ? -1 : 1;
            }
            if ($a['orderRank'] !== $b['orderRank']) {
                return $a['orderRank'] < $b['orderRank'] ? -1 : 1;
            }

            return $a['idProduct'] <=> $b['idProduct'];
        });

        return $scored[0]['row'] ?? null;
    }

    /**
     * Return the preferred index in the flat ids array for the given language (0-based).
     * Reads config('services.cardmarket.preferred_id_index_by_language').
     */
    private function preferredIndexForLanguage(string $lang): ?int
    {
        $map = config('services.cardmarket.preferred_id_index_by_language');
        if (is_string($map)) {
            // Parse format like "en:0,ja:1,de:0"
            $pairs = array_filter(array_map('trim', explode(',', $map)), fn ($v) => $v !== '');
            $assoc = [];
            foreach ($pairs as $p) {
                [$k, $v] = array_pad(array_map('trim', explode(':', $p, 2)), 2, null);
                // Languages are normalized like the card's one ("ja" and "jp" are the same)
                $configuredLanguage = CardLanguage::fromProviderValue($k);
                if ($configuredLanguage !== null && $v !== null && $v !== '' && is_numeric($v)) {
                    $assoc[$configuredLanguage->value] = (int) $v;
                }
            }
            $map = $assoc;
        }
        if (is_array($map)) {
            $key = strtolower(trim($lang));
            if (array_key_exists($key, $map) && is_numeric($map[$key])) {
                $idx = (int) $map[$key];

                return $idx >= 0 ? $idx : null;
            }
        }

        return null;
    }
}
