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
    private const CHEAPSHARK_PAGE_SIZE = 30;
    private const CHEAPSHARK_EXTRA_PAGE_BUFFER = 2;
    private const AAA_SOURCE_SIZE = 600;
    private const AAA_TARGET_SIZE = 10;

    private function uniqueDealKey(array $deal): ?string
    {
        return $deal['steamAppID']
            ?? $deal['gameID']
            ?? $deal['title']
            ?? $deal['gameName']
            ?? null;
    }

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
                $payload = CheapSharkService::client()->get('https://www.cheapshark.com/api/1.0/deals', [
                    'sortBy' => 'DealRating',
                    'pageSize' => self::CHEAPSHARK_PAGE_SIZE,
                    'pageNumber' => $page,
                ])->json();
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

    public function popularAAA(): Collection
    {
        $deals = $this->recommend(self::AAA_SOURCE_SIZE);

        $aaa = $deals->filter(function ($deal) {
            return ($deal['normalPrice'] ?? 0) >= 29.99 && ($deal['steamRatingCount'] ?? 0) >= 500 && ($deal['metacriticScore'] ?? 0) >= 75;
        });

        return $aaa->sortByDesc('savings')->sortByDesc('dealRating')->sortBy('salePrice')->values()->take(self::AAA_TARGET_SIZE);
    }

    private function collapseDealsByGame(Collection $deals): Collection
    {
        return $deals
            ->filter(fn ($deal) => !empty($deal['steamAppID']))
            ->groupBy(fn ($deal) => $this->uniqueDealKey($deal))
            ->map(fn (Collection $group) => CheapSharkService::selectPreferredDeal($group->all()))
            ->filter()
            ->values();
    }

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

        foreach ($missingIds as $id) {
            $res = Http::get('https://store.steampowered.com/api/appdetails', [
                'appids' => $id,
            ])->json();

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

        if ($missingIds->isNotEmpty()) {
            $this->saveSteamGenreCache($cached);
        }

        return $result;
    }

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

    private function saveSteamGenreCache(array $cache): void
    {
        if (!$this->hasRecommendationTable()) {
            return;
        }

        DB::table('game_recommendations')->updateOrInsert(
            ['type' => self::STEAM_GENRE_CACHE_TYPE],
            [
                'payload' => json_encode($cache, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

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

    public function buildDailyCache(): bool
    {
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

    private function syncGamesTable(Collection $deals): void
    {
        foreach ($deals as $deal) {
            $cheapsharkId = $deal['gameID'] ?? null;

            if (!$cheapsharkId) {
                continue;
            }

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

    private function saveCache(string $type, Collection $data): void
    {
        if (!$this->hasRecommendationTable()) {
            return;
        }

        DB::table('game_recommendations')->updateOrInsert(
            ['type' => $type],
            [
                'payload' => $data->values()->toJson(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

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

    private function hasRecommendationTable(): bool
    {
        return Schema::hasTable('game_recommendations');
    }
}
