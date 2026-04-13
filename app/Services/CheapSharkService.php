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
    private const SEARCH_BURST_WAIT_MICROSECONDS = 1000000;
    private const SEARCH_BURST_RESULT_WAIT_MICROSECONDS = 100000;
    private const SEARCH_BURST_RESULT_WAIT_RETRIES = 40;
    private const MAX_REQUESTS_PER_SECOND = 3;
    private const RATE_LIMIT_TTL_SECONDS = 2;
    private const RATE_LIMIT_WAIT_MICROSECONDS = 100000;
    private const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_SEARCH = 'search';
    public const PRIORITY_PRICE_CHECK = 'normal';
    public const PRIORITY_BUILD_CACHE = 'normal';

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

    // Converts a store ID into a human-readable store name.
    public function getStoreName(int $storeId): string
    {
        return self::resolveStoreName($storeId);
    }

    // Returns the full static map of CheapShark store IDs.
    public static function getAllStores(): array
    {
        return self::STORES;
    }

    // Resolves nullable/mixed store IDs and returns a safe display label.
    public static function resolveStoreName($storeId): string
    {
        $storeId = self::normalizeStoreId($storeId);

        if ($storeId === null) {
            return 'Unknown';
        }

        return self::STORES[$storeId] ?? "Store #{$storeId}";
    }

    // Chooses one best deal from a list by price/savings/store/deal ordering.
    public static function selectPreferredDeal(array $deals): ?array
    {
        return collect(self::normalizeDealList($deals))
            ->filter(fn ($deal) => is_array($deal))
            ->sort(fn (array $left, array $right) => self::compareDeals($left, $right))
            ->first();
    }

            // Accepts an API payload and keeps only list-style array deal entries.
    public static function normalizeDealList($payload): array
    {
        if (!is_array($payload) || !array_is_list($payload)) {
            return [];
        }

        return array_values(array_filter($payload, fn ($deal) => is_array($deal)));
    }

    // Canonicalizes search text for cache keys and upstream query consistency.
    public static function normalizeSearchQuery(string $query): string
    {
        // Normalize user input so cache keys and upstream queries stay consistent.
        $normalized = mb_strtolower(trim($query));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized);

        return is_string($normalized) ? $normalized : '';
    }

    // Reads a standard API error string from a response payload.
    public static function extractApiError($payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $error = $payload['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    // Builds the shared HTTP client used for CheapShark calls.
    public static function client(): PendingRequest
    {
        $request = Http::timeout(15);

        // Local PHP setups on Windows can fail SSL verification without a CA bundle.
        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    // Performs a GET request with rate-limit coordination and returns the raw response.
    public static function throttledGet(string $url, array $query = [], string $priority = self::PRIORITY_NORMAL): Response
    {

        // Store the API call count in the cache and display it in the terminal (starting from 1 if uninitialized)
        $count = Cache::get('cheapshark:api-call-count', 0);
        $count++;
        Cache::put('cheapshark:api-call-count', $count, now()->addDays(7));
        // display the count in the terminal for monitoring purposes
        echo "★ CheapShark API invoked: {$count} times\n";

        self::waitForRateLimitSlot($priority);

        return self::client()->get($url, $query);
    }

    /**
     * Backward-compatible alias used by other services.
     */
    public static function rateLimitedCall(string $url, array $query = [], string $priority = self::PRIORITY_NORMAL): Response
    {
        return self::throttledGet($url, $query, $priority);
    }

    // Sends title-search to CheapShark /games and returns decoded JSON.
    public function search($query)
    {
        $response = self::throttledGet(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "title"=>$query,
                "limit"=>self::SEARCH_GAME_LIMIT
            ],
            self::PRIORITY_SEARCH,
        );

        return $response->json();

    }

    // Sends deal-search to CheapShark /deals and returns decoded JSON.
    public function searchDeals($query)
    {
        $response = self::throttledGet(
            "https://www.cheapshark.com/api/1.0/deals",
            [
                "title" => $query,
                "pageSize" => self::SEARCH_DEALS_PAGE_SIZE
            ],
            self::PRIORITY_SEARCH,
        );

        return $response->json();
    }

    // Batches same-second normalized deal searches and shares one short-lived result per query.
    public function searchDealsAggregated(string $query): array
    {
        $normalized = $query;
        $queryHash = md5($normalized);
        $resultKey = self::searchBurstResultKey($queryHash);

        // Fast path: reuse a recent burst result if available.
        $cachedResult = Cache::get($resultKey);
        if (is_array($cachedResult)) {
            return $cachedResult;
        }

        $window = now()->format('YmdHis');
        $batchKey = self::searchBurstBatchKey($window);
        $dispatchKey = self::searchBurstDispatchKey($window);

        // Enqueue into the FIFO list for this second window, skipping duplicates.
        $lock = Cache::lock('cheapshark:search-burst-lock:' . $window, 2);
        $lock->block(1, function () use ($batchKey, $normalized) {
            $queue = Cache::get($batchKey, []);
            if (!is_array($queue)) {
                $queue = [];
            }

            // Deduplicate the same normalized query within the same second-window queue.
            $alreadyQueued = collect($queue)->contains('normalized', $normalized);
            if (!$alreadyQueued) {
                $queue[] = ['normalized' => $normalized];
                Cache::put($batchKey, $queue, now()->addSeconds(3));
            }
        });

        // First request to win the dispatch lock processes the whole FIFO queue in order.
        if (Cache::add($dispatchKey, 1, now()->addSeconds(2))) {
            usleep(self::SEARCH_BURST_WAIT_MICROSECONDS);

            $queue = Cache::get($batchKey, []);
            if (is_array($queue)) {
                // Process in FIFO order so earlier arrivals are served first.
                foreach ($queue as $item) {
                    $payload = $this->searchDeals((string) $item['normalized']);
                    Cache::put(
                        self::searchBurstResultKey(md5((string) $item['normalized'])),
                        $payload,
                        now()->addSeconds(15),
                    );
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

        return [
            // Caller can retry shortly; no direct fallback API call here by design.
            'error' => 'search_burst_timeout',
            'retryAfterSeconds' => 1,
        ];
    }

    // Fetches one game detail payload from CheapShark /games?id=...
    public function deals($gameId)
    {

        $response = self::throttledGet(
            "https://www.cheapshark.com/api/1.0/games",
            [
                "id"=>$gameId
            ]
        );

        return $response->json();
    }

    // Comparator used to rank deals: lowest price, highest savings, lowest store ID, then deal ID.
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

    // Extracts numeric price with field fallbacks for mixed CheapShark payloads.
    private static function dealPrice(array $deal): float
    {
        return (float) (
            $deal['price']
            ?? $deal['salePrice']
            ?? $deal['cheapest']
            ?? INF
        );
    }

    // Computes savings percent from payload or derives it from retail/current price.
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

    // Normalizes optional store IDs and supports a default sentinel.
    private static function normalizeStoreId($storeId, ?int $default = null): ?int
    {
        if ($storeId === null || $storeId === '') {
            return $default;
        }

        return (int) $storeId;
    }

    // Coordinates per-second request slots across priorities using cache-based counters/locks.
    private static function waitForRateLimitSlot(string $priority): void
    {
        $isSearch = $priority === self::PRIORITY_SEARCH;

        if ($isSearch) {
            self::incrementSearchWaiters();
        }

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
                    $normalKey = 'cheapshark:rate:normal:' . $second;

                    $total = (int) Cache::get($totalKey, 0);
                    $normal = (int) Cache::get($normalKey, 0);
                    $searchWaiters = (int) Cache::get('cheapshark:search-waiters', 0);

                    // Reserve up to two slots for active search traffic to keep UI responsive.
                    $reserved = min($searchWaiters, self::MAX_REQUESTS_PER_SECOND - 1);
                    $normalLimit = self::MAX_REQUESTS_PER_SECOND - $reserved;

                    $canProceed = false;

                    if ($isSearch) {
                        $canProceed = $total < self::MAX_REQUESTS_PER_SECOND;
                    } else {
                        $canProceed = $total < self::MAX_REQUESTS_PER_SECOND
                            && $normal < $normalLimit;
                    }

                    if ($canProceed) {
                        Cache::put($totalKey, $total + 1, now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));

                        if (!$isSearch) {
                            Cache::put($normalKey, $normal + 1, now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));
                        }

                        return;
                    }
                } finally {
                    $lock->release();
                }

                usleep(self::RATE_LIMIT_WAIT_MICROSECONDS);
            }
        } finally {
            if ($isSearch) {
                self::decrementSearchWaiters();
            }
        }
    }

    // Tracks active search waiters so search traffic can reserve request slots.
    private static function incrementSearchWaiters(): void
    {
        Cache::add('cheapshark:search-waiters', 0, now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));
        Cache::increment('cheapshark:search-waiters');
    }

    // Decrements search waiter count safely under a short cache lock.
    private static function decrementSearchWaiters(): void
    {
        $lock = Cache::lock('cheapshark:search-waiters-lock', 1);

        if (!$lock->get()) {
            return;
        }

        try {
            $current = (int) Cache::get('cheapshark:search-waiters', 0);
            Cache::put('cheapshark:search-waiters', max(0, $current - 1), now()->addSeconds(self::RATE_LIMIT_TTL_SECONDS));
        } finally {
            $lock->release();
        }
    }

    // Builds cache key for one-second burst queue storage.
    private static function searchBurstBatchKey(string $window): string
    {
        return 'cheapshark:search-burst:batch:' . $window;
    }

    // Builds cache key for one-second burst dispatcher election.
    private static function searchBurstDispatchKey(string $window): string
    {
        return 'cheapshark:search-burst:dispatch:' . $window;
    }

    // Builds cache key for short-lived per-query burst results.
    private static function searchBurstResultKey(string $hash): string
    {
        return 'cheapshark:search-burst:result:' . $hash;
    }
}
