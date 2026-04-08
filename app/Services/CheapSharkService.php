<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;

class CheapSharkService
{
    private const SEARCH_GAME_LIMIT = 60;
    private const SEARCH_DEALS_PAGE_SIZE = 60;
    private const SEARCH_BURST_MAX_POOL_SIZE = 8;
    private const SEARCH_BURST_WAIT_MICROSECONDS = 1000000;
    private const SEARCH_BURST_RESULT_WAIT_MICROSECONDS = 50000;
    private const SEARCH_BURST_RESULT_WAIT_RETRIES = 20;
    private const SEARCH_API_CACHE_TTL_MINUTES = 3;
    private const MAX_REQUESTS_PER_SECOND = 3;
    private const RATE_LIMIT_TTL_SECONDS = 2;
    private const RATE_LIMIT_WAIT_MICROSECONDS = 100000;
    public const PRIORITY_SEARCH = 'search';
    public const PRIORITY_PRICE_CHECK = 'price_check';
    public const PRIORITY_BUILD_CACHE = 'build_cache';

    public const STORES = [
        1 => 'Steam',
        2 => 'GamersGate',
        3 => 'GreenManGaming',
        4 => 'Amazon',
        5 => 'GameStop',
        6 => 'Direct2Drive',
        7 => 'GOG',
        8 => 'Origin',
        9 => 'Get Games',
        10 => 'Shiny Loot',
        11 => 'Humble Store',
        12 => 'Desura',
        13 => 'Uplay',
        14 => 'IndieGameStand',
        15 => 'Fanatical',
        16 => 'Gamesrocket',
        17 => 'Games Republic',
        18 => 'SilaGames',
        19 => 'Playfield',
        20 => 'ImperialGames',
        21 => 'WinGameStore',
        22 => 'FunStockDigital',
        23 => 'GameBillet',
        24 => 'Voidu',
        25 => 'Epic Games Store',
        26 => 'Razer Game Store',
        27 => 'Gamesplanet',
        28 => 'Gamesload',
        29 => '2Game',
        30 => 'IndieGala',
        31 => 'Blizzard Shop',
        32 => 'AllYouPlay',
        33 => 'DLGamer',
        34 => 'Noctre',
        35 => 'DreamGame',
    ];

    public static function getAllStores(): array
    {
        return self::STORES;
    }

    public static function resolveStoreName($storeId): string
    {
        $storeId = self::normalizeStoreId($storeId);

        if ($storeId === null) {
            return 'Unknown';
        }

        return self::STORES[$storeId] ?? "Store #{$storeId}";
    }

    public static function selectPreferredDeal(array $deals): ?array
    {
        return collect(self::normalizeDealList($deals))
            ->filter(fn ($deal) => is_array($deal))
            ->sort(fn (array $left, array $right) => self::compareDeals($left, $right))
            ->first();
    }

    public static function normalizeDealList($payload): array
    {
        if (!is_array($payload) || !array_is_list($payload)) {
            return [];
        }

        return array_values(array_filter($payload, fn ($deal) => is_array($deal)));
    }

    public static function extractApiError($payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $error = $payload['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    public static function client(): PendingRequest
    {
        $request = Http::timeout(15);

        // Local PHP setups on Windows can fail SSL verification without a CA bundle.
        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * Rate-limited API call with 3-tier priority scheduling.
     *
     * ALL CheapShark API access must go through this method.
     * Enforces a global 3 QPS cap; priority order: search > price_check > build_cache.
     */
    public static function rateLimitedCall(string $url, array $query = [], string $priority = self::PRIORITY_BUILD_CACHE): Response
    {
        self::waitForRateLimitSlot($priority);

        return self::client()->get($url, $query);
    }

    public function searchDeals($query)
    {
        $response = self::rateLimitedCall(
            "https://www.cheapshark.com/api/1.0/deals",
            [
                "title" => $query,
                "pageSize" => self::SEARCH_DEALS_PAGE_SIZE
            ],
            self::PRIORITY_SEARCH,
        );

        return $response->json();
    }

    public function searchDealsAggregated(string $query): array
    {
        $normalized = mb_strtolower(preg_replace('/\s+/', ' ', trim($query)));
        $queryHash = md5($normalized);
        $resultKey = self::searchBurstResultKey($queryHash);
        $apiCacheKey = self::searchApiCacheKey($normalized);

        $cachedApiPayload = Cache::get($apiCacheKey);
        if (is_array($cachedApiPayload)) {
            return $cachedApiPayload;
        }

        $cachedResult = Cache::get($resultKey);
        if (is_array($cachedResult)) {
            return $cachedResult;
        }

        $window = now()->format('YmdHis');
        $batchKey = self::searchBurstBatchKey($window);
        $dispatchKey = self::searchBurstDispatchKey($window);
        $shouldBypassBatch = false;

        $lock = Cache::lock('cheapshark:search-burst-lock:' . $window, 2);
        $lock->block(1, function () use ($batchKey, $normalized, $query, $resultKey, &$shouldBypassBatch) {
            $apiCached = Cache::get(self::searchApiCacheKey($normalized));
            if (is_array($apiCached)) {
                Cache::put($resultKey, $apiCached, now()->addSeconds(15));
                return;
            }

            $batch = Cache::get($batchKey, []);
            if (!is_array($batch)) {
                $batch = [];
            }

            if (!array_key_exists($normalized, $batch) && count($batch) >= self::SEARCH_BURST_MAX_POOL_SIZE) {
                $shouldBypassBatch = true;
                return;
            }

            $batch[$normalized] = $query;
            Cache::put($batchKey, $batch, now()->addSeconds(3));
        });

        if ($shouldBypassBatch) {
            // Keep search latency stable under burst pressure by bypassing the batch once the pool is full.
            $payload = $this->searchDeals($query);
            Cache::put($apiCacheKey, $payload, now()->addMinutes(self::SEARCH_API_CACHE_TTL_MINUTES));
            Cache::put($resultKey, $payload, now()->addSeconds(15));

            return $payload;
        }

        if (Cache::add($dispatchKey, 1, now()->addSeconds(2))) {
            usleep(self::SEARCH_BURST_WAIT_MICROSECONDS);

            $queries = Cache::get($batchKey, []);
            if (is_array($queries)) {
                foreach ($queries as $queuedNormalized => $queuedQuery) {
                    $queuedApiCacheKey = self::searchApiCacheKey((string) $queuedNormalized);
                    $cachedPayload = Cache::get($queuedApiCacheKey);

                    if (is_array($cachedPayload)) {
                        Cache::put(self::searchBurstResultKey(md5((string) $queuedNormalized)), $cachedPayload, now()->addSeconds(15));
                        continue;
                    }

                    $payload = $this->searchDeals((string) $queuedQuery);
                    Cache::put($queuedApiCacheKey, $payload, now()->addMinutes(self::SEARCH_API_CACHE_TTL_MINUTES));
                    Cache::put(self::searchBurstResultKey(md5((string) $queuedNormalized)), $payload, now()->addSeconds(15));
                }
            }
        }

        for ($i = 0; $i < self::SEARCH_BURST_RESULT_WAIT_RETRIES; $i++) {
            $payload = Cache::get($resultKey);
            if (is_array($payload)) {
                return $payload;
            }

            usleep(self::SEARCH_BURST_RESULT_WAIT_MICROSECONDS);
        }

        // Fallback if batch dispatcher failed for any reason.
        return $this->searchDeals($query);
    }

    public function deals($gameId)
    {

        $response = self::rateLimitedCall(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "id"=>$gameId
            ]
        );

        return $response->json();
    }

    private static function compareDeals(array $left, array $right): int
    {
        $priceComparison = self::dealPrice($left) <=> self::dealPrice($right);

        if ($priceComparison !== 0) {
            return $priceComparison;
        }

        $savingsComparison = self::dealSavings($right) <=> self::dealSavings($left);

        if ($savingsComparison !== 0) {
            return $savingsComparison;
        }

        $storeComparison = self::normalizeStoreId($left['storeID'] ?? null, PHP_INT_MAX)
            <=> self::normalizeStoreId($right['storeID'] ?? null, PHP_INT_MAX);

        if ($storeComparison !== 0) {
            return $storeComparison;
        }

        return strcmp((string) ($left['dealID'] ?? ''), (string) ($right['dealID'] ?? ''));
    }

    private static function dealPrice(array $deal): float
    {
        return (float) (
            $deal['price']
            ?? $deal['salePrice']
            ?? $deal['cheapest']
            ?? INF
        );
    }

    private static function dealSavings(array $deal): float
    {
        if (isset($deal['savings'])) {
            return (float) $deal['savings'];
        }

        $price = self::dealPrice($deal);
        $retail = (float) ($deal['retailPrice'] ?? $deal['normalPrice'] ?? $price);

        if ($retail <= 0 || $price >= $retail) {
            return 0.0;
        }

        return (1 - ($price / $retail)) * 100;
    }

    private static function normalizeStoreId($storeId, ?int $default = null): ?int
    {
        if ($storeId === null || $storeId === '') {
            return $default;
        }

        return (int) $storeId;
    }

    private static function waitForRateLimitSlot(string $priority): void
    {
        self::incrementWaiters($priority);

        try {
            while (true) {
                $second = now()->format('YmdHis');
                $lock = Cache::lock('cheapshark:rate-lock:' . $second, 1);

                if (!$lock->get()) {
                    usleep(self::RATE_LIMIT_WAIT_MICROSECONDS);
                    continue;
                }

                try {
                    $totalKey = 'cheapshark:rate:total:' . $second;

                    $total = (int) Cache::get($totalKey, 0);
                    $searchWaiters = self::waiters(self::PRIORITY_SEARCH);
                    $priceWaiters = self::waiters(self::PRIORITY_PRICE_CHECK);
                    $buildWaiters = self::waiters(self::PRIORITY_BUILD_CACHE);

                    $canProceed = false;

                    if ($priority === self::PRIORITY_SEARCH) {
                        $canProceed = $total < self::MAX_REQUESTS_PER_SECOND;
                    } elseif ($priority === self::PRIORITY_PRICE_CHECK) {
                        $canProceed = $total < self::MAX_REQUESTS_PER_SECOND
                            && $searchWaiters === 0;
                    } else {
                        $canProceed = $total < self::MAX_REQUESTS_PER_SECOND
                            && $searchWaiters === 0
                            && $priceWaiters === 0;
                    }

                    if ($canProceed) {
                        Cache::put($totalKey, $total + 1, now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));

                        return;
                    }
                } finally {
                    $lock->release();
                }

                usleep(self::RATE_LIMIT_WAIT_MICROSECONDS);
            }
        } finally {
            self::decrementWaiters($priority);
        }
    }

    private static function incrementWaiters(string $priority): void
    {
        $key = self::waiterKey($priority);

        Cache::add($key, 0, now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));
        Cache::increment($key);
    }

    private static function decrementWaiters(string $priority): void
    {
        $key = self::waiterKey($priority);
        $lock = Cache::lock($key . ':lock', 1);

        if (!$lock->get()) {
            return;
        }

        try {
            $current = (int) Cache::get($key, 0);
            Cache::put($key, max(0, $current - 1), now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));
        } finally {
            $lock->release();
        }
    }

    private static function waiters(string $priority): int
    {
        return (int) Cache::get(self::waiterKey($priority), 0);
    }

    private static function waiterKey(string $priority): string
    {
        return 'cheapshark:waiters:' . $priority;
    }

    private static function searchBurstBatchKey(string $window): string
    {
        return 'cheapshark:search-burst:batch:' . $window;
    }

    private static function searchBurstDispatchKey(string $window): string
    {
        return 'cheapshark:search-burst:dispatch:' . $window;
    }

    private static function searchBurstResultKey(string $hash): string
    {
        return 'cheapshark:search-burst:result:' . $hash;
    }

    private static function searchApiCacheKey(string $normalized): string
    {
        return 'cheapshark:search-api:' . md5($normalized);
    }
}
