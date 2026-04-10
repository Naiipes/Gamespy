<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class GameDiscoveryService
{
    private ?Collection $cachedDeals = null;

    private const STEAM_GENRE_CACHE_TYPE = 'steam_genres';
    private const RECOMMENDATION_TARGET_SIZE = 500;
    private const GENRE_TARGET_SIZE = 20;
    private const CHEAPSHARK_PAGE_SIZE = 60;
    private const CHEAPSHARK_EXTRA_PAGE_BUFFER = 1;
    private const AAA_SOURCE_SIZE = 600;
    private const AAA_TARGET_SIZE = 10;

    
    private const STEAM_MAX_REQUESTS_PER_SECOND = 10;
    private const STEAM_RATE_LIMIT_TTL_SECONDS = 2;
    private const STEAM_RATE_LIMIT_WAIT_MICROSECONDS = 100000;

    private static function waitForSteamRateLimitSlot(): void
    {
        while (true) {
            $second = now()->format('YmdHis');
            $lock = \Cache::lock('steam:rate-lock:' . $second, 1);

            if (!$lock->get()) {
                usleep(self::STEAM_RATE_LIMIT_WAIT_MICROSECONDS);
                continue;
            }

            try {
                $totalKey = 'steam:rate:total:' . $second;
                $total = (int) \Cache::get($totalKey, 0);

                if ($total < self::STEAM_MAX_REQUESTS_PER_SECOND) {
                    \Cache::put($totalKey, $total + 1, now()->addSeconds(self::STEAM_RATE_LIMIT_TTL_SECONDS));
                    return;
                }
            } finally {
                $lock->release();
            }

            usleep(self::STEAM_RATE_LIMIT_WAIT_MICROSECONDS);
        }
    }
    
    // Returns a stable per-game key from deal payload fields used across different endpoints.
    private function uniqueDealKey(array $deal): ?string
    {
        return $deal['steamAppID']
            ?? $deal['gameID']
            ?? $deal['title']
            ?? $deal['gameName']
            ?? null;
    }

            // Pulls top CheapShark deals, deduplicates by game, and returns up to requested size.
    public function recommend(int $size): Collection
    {
        if ($this->cachedDeals !== null) {
            return $this->cachedDeals->take($size)->values();
        }

        $page = 0;
        $maxPages = (int) ceil($size / self::CHEAPSHARK_PAGE_SIZE) + self::CHEAPSHARK_EXTRA_PAGE_BUFFER;
        $allDeals = collect();
        $unique = collect();

        while ($unique->count() < $size && $page < $maxPages) {
            try {
                // Pull top-rated deals page-by-page until we have enough unique games.
                $payload = CheapSharkService::rateLimitedCall('https://www.cheapshark.com/api/1.0/deals', [
                    'sortBy' => 'DealRating',
                    'pageSize' => self::CHEAPSHARK_PAGE_SIZE,
                    'pageNumber' => $page,
                ], CheapSharkService::PRIORITY_BUILD_CACHE
                )->json();
            } catch (ConnectionException $e) {
                logger()->warning('Game discovery deal refresh stopped because CheapShark could not be reached.', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $deals = CheapSharkService::normalizeDealList($payload);

            if ($error = CheapSharkService::extractApiError($payload)) {
                logger()->warning('Game discovery deal refresh received an error payload from CheapShark.', [
                    'page' => $page,
                    'error' => $error,
                ]);
                break;
            }

            if (empty($deals)) {
                break;
            }

            $allDeals = $allDeals
                ->merge($deals)
                ->values();

            $unique = $this->collapseDealsByGame($allDeals);

            $page++;
        }

        $this->cachedDeals = $unique->take($size)->values();

        return $this->cachedDeals;
    }

    // Derives an AAA subset from recommendations and sorts by value/quality.
    public function popularAAA(): Collection
    {
        $deals = $this->recommend(self::AAA_SOURCE_SIZE);

        $aaa = $deals->filter(function ($deal) {
            return ($deal['normalPrice'] ?? 0) >= 39.99;
        });

        return $aaa->sortByDesc('savings')->sortByDesc('dealRating')->sortBy('salePrice')->values()->take(self::AAA_TARGET_SIZE);
    }

    // Collapses many deal rows into one preferred deal per Steam app.
    private function collapseDealsByGame(Collection $deals): Collection
    {
        return $deals
            ->filter(fn ($deal) => !empty($deal['steamAppID']))
            // Keep one preferred deal per game so downstream sections are deduplicated.
            ->groupBy(fn ($deal) => $this->uniqueDealKey($deal))
            ->map(fn (Collection $group) => CheapSharkService::selectPreferredDeal($group->all()))
            ->filter()
            ->values();
    }

            // Fetches and caches Steam genre tags for deal app IDs.
    private function fetchSteamGenres(Collection $deals): array
{
    $appIds = $deals
        ->pluck('steamAppID')
        ->filter()
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values();

    $cached = $this->getSteamGenreCache();
    $result = [];

    foreach ($appIds as $id) {
        if (array_key_exists($id, $cached)) {
            $result[$id] = $cached[$id];
        }
    }

    $missingIds = $appIds
        ->reject(fn ($id) => array_key_exists($id, $cached))
        ->values();

    if ($missingIds->isEmpty()) {
        return $result;
    }

    // Batch Steam API requests (10 appids per request instead of 1 per request)
    $batchSize = 10;
    foreach (array_chunk($missingIds->all(), $batchSize) as $batch) {
        self::waitForSteamRateLimitSlot();
        try {
            $res = Http::get('https://store.steampowered.com/api/appdetails', [
                'appids' => implode(',', $batch),
            ])->json();

            foreach ($batch as $id) {
                $genres = [];

                if (!empty($res[$id]['success']) && !empty($res[$id]['data']['genres'])) {
                    $genres = collect($res[$id]['data']['genres'])
                        ->pluck('description')
                        ->map(fn ($g) => strtolower($g))
                        ->values()
                        ->all();
                }

                // Cache misses (including empty genre arrays) to avoid re-fetching next run.
                $cached[$id] = $genres;
                $result[$id] = $genres;
            }
        } catch (ConnectionException $e) {
            logger()->warning('Steam genre batch fetch failed.', [
                'batch_size' => count($batch),
                'error' => $e->getMessage(),
            ]);
            // Mark missing batch items as empty genre to avoid re-fetching
            foreach ($batch as $id) {
                $cached[$id] = [];
                $result[$id] = [];
            }
        }
    }

    if ($missingIds->isNotEmpty()) {
        $this->saveSteamGenreCache($cached);
    }

    return $result;
}

    // Loads persisted Steam genre cache payload from recommendation storage.
    private function getSteamGenreCache(): array
    {
        if (!$this->hasRecommendationTable()) {
            return [];
        }

        $json = DB::table('game_recommendations')
            ->where('type', self::STEAM_GENRE_CACHE_TYPE)
            ->value('payload');

        if (!$json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    // Persists Steam genre cache payload for reuse across build runs.
private function saveSteamGenreCache(array $newCache): void
{
    if (!$this->hasRecommendationTable()) {
        return;
    }
    
    $json = DB::table('game_recommendations')
        ->where('type', self::STEAM_GENRE_CACHE_TYPE)
        ->value('payload');
    $existing = $json ? json_decode($json, true) : [];
    if (!is_array($existing)) $existing = [];
    
    $merged = array_merge($existing, $newCache);
    DB::table('game_recommendations')->updateOrInsert(
        ['type' => self::STEAM_GENRE_CACHE_TYPE],
        [
            'payload' => json_encode($merged, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );
}

    // Filters deals by normalized Steam genre tag.
    private function filterByGenre(Collection $deals, array $steamGenres, string $genre): Collection
    {
        $genre = strtolower($genre);

        return $deals
            ->filter(function ($deal) use ($steamGenres, $genre) {
                $id = $deal['steamAppID'] ?? null;
                if (!$id || !isset($steamGenres[$id])) {
                    return false;
                }

                return in_array($genre, $steamGenres[$id], true);
            })
            ->values();
    }

            // Builds and stores all discovery sections (genre buckets, recommend, AAA) for homepage usage.
    public function buildDailyCache(): bool
    {
        // Build all homepage sections from the same source snapshot for consistency.
        $deals = $this->recommend(self::RECOMMENDATION_TARGET_SIZE);

        if ($deals->isEmpty()) {
            logger()->warning('Game discovery cache refresh skipped because no valid CheapShark deals were returned.');
            return false;
        }

        $this->syncGamesTable($deals);

        $steamGenres = $this->fetchSteamGenres($deals);

        $genres = [
            'action',
            'adventure',
            'rpg',
            'strategy',
            'sports',
            'simulation',
            'racing',
            'indie',
            'casual'
        ];


        foreach ($genres as $g) {
            $filtered = $this->filterByGenre($deals, $steamGenres, $g)->take(self::GENRE_TARGET_SIZE)->values();

            $this->saveCache($g, $filtered);
        }

        $this->saveCache('recommend', $deals->take(self::RECOMMENDATION_TARGET_SIZE)->values());

        $this->saveCache('aaa', $this->popularAAA());

        return true;
    }

    // Upserts local games table from selected discovery deal payloads.
    private function syncGamesTable(Collection $deals): void
    {
        foreach ($deals as $deal) {
            $cheapsharkId = $deal['gameID'] ?? null;

            if (!$cheapsharkId) {
                continue;
            }

            // Upsert keeps local game metadata aligned with latest discovery payload.
            Game::updateOrCreate(
                ['cheapshark_id' => (string) $cheapsharkId],
                [
                    'title' => $deal['title'] ?? $deal['gameName'] ?? 'Unknown',
                    'thumb' => $deal['thumb'] ?? null,
                    'cheapest_price' => $deal['salePrice'] ?? $deal['cheapest'] ?? null,
                    'steamAppID' => $deal['steamAppID'] ?? null,
                ],
            );
        }
    }

    // Writes one recommendation bucket payload to storage by type key.
private function saveCache(string $type, Collection $data): void
{
    if (!$this->hasRecommendationTable()) {
        return;
    }
    
    $json = DB::table('game_recommendations')->where('type', $type)->value('payload');
    $existing = $json ? json_decode($json, true) : [];
    if (!is_array($existing)) $existing = [];
    
    $existingGames = collect($existing);
    $newGames = $data->filter(function($newGame) use ($existingGames) {
        $newId = $newGame['steamAppID'] ?? $newGame['gameID'] ?? $newGame['title'] ?? null;
        return !$existingGames->contains(function($exist) use ($newId) {
            $existId = $exist['steamAppID'] ?? $exist['gameID'] ?? $exist['title'] ?? null;
            return $existId && $newId && $existId == $newId;
        });
    });
    $merged = $existingGames->merge($newGames)->values();
    DB::table('game_recommendations')->updateOrInsert(
        ['type' => $type],
        [
            'payload' => $merged->toJson(),
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );
}

    // Reads one genre bucket from persisted recommendation cache.
    public function genre(string $genre, int $size = self::GENRE_TARGET_SIZE): Collection
    {
        if (!$this->hasRecommendationTable()) {
            return collect();
        }

        $json = DB::table('game_recommendations')->where('type', strtolower($genre))->value('payload');

        if (!$json) {
            return collect();
        }

        return collect(json_decode($json, true))->take($size)->values();
    }

    // Reads the cached recommend bucket for homepage catalog cards.
    public function cachedRecommend(int $size = self::RECOMMENDATION_TARGET_SIZE): Collection
    {
        if (!$this->hasRecommendationTable()) {
            return collect();
        }

        $json = DB::table('game_recommendations')->where('type', 'recommend')->value('payload');

        if (!$json) {
            return collect();
        }

        return collect(json_decode($json, true))->take($size)->values();
    }

    // Reads the cached AAA bucket for homepage carousel cards.
    public function cachedAAA(int $size = self::AAA_SOURCE_SIZE): Collection
    {
        if (!$this->hasRecommendationTable()) {
            return collect();
        }

        $json = DB::table('game_recommendations')->where('type', 'aaa')->value('payload');

        if (!$json) {
            return collect();
        }

        return collect(json_decode($json, true))->take($size)->values();
    }

    // Guards DB-backed cache access when migrations have not yet created the table.
    private function hasRecommendationTable(): bool
    {
        return Schema::hasTable('game_recommendations');
    }
}
