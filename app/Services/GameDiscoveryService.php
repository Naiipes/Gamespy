<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GameDiscoveryService
{
    private ?Collection $cachedDeals = null;

    private const STEAM_GENRE_CACHE_TYPE = 'steam_genres';
    private const RECOMMENDATION_TARGET_SIZE = 200;
    private const CHEAPSHARK_PAGE_SIZE = 30;
    private const CHEAPSHARK_EXTRA_PAGE_BUFFER = 2;
    private const AAA_SOURCE_SIZE = 60;

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
        $unique = collect();

        while ($unique->count() < $size && $page < $maxPages) {
            $deals = Http::get('https://www.cheapshark.com/api/1.0/deals', [
                'sortBy' => 'DealRating',
                'pageSize' => self::CHEAPSHARK_PAGE_SIZE,
                'pageNumber' => $page,
            ])->json();

            if (empty($deals)) {
                break;
            }

            $unique = $unique
                ->merge($deals)
                ->filter(fn ($deal) => !empty($deal['steamAppID']))
                ->unique(fn ($deal) => $this->uniqueDealKey($deal))
                ->values();

            $page++;
        }

        $this->cachedDeals = $unique->take($size)->values();

        return $this->cachedDeals;
    }

    public function popularAAA(): Collection
    {
        $deals = $this->recommend(self::AAA_SOURCE_SIZE);

        $aaa = $deals->filter(function ($deal) {
            return ($deal['normalPrice'] ?? 0) >= 39.99 && ($deal['steamRatingCount'] ?? 0) >= 500 && ($deal['metacriticScore'] ?? 0) >= 75;
        });

        return $aaa->sortByDesc('savings')->sortByDesc('dealRating')->sortBy('salePrice')->values()->take(5);
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

    public function buildDailyCache(): void
    {
        $deals = $this->recommend(self::RECOMMENDATION_TARGET_SIZE);

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
            'casual',
            'massively multiplayer',
            'free to play'
        ];


        foreach ($genres as $g) {
            $filtered = $this->filterByGenre($deals, $steamGenres, $g)->take(20)->values();

            $this->saveCache($g, $filtered);
        }

        $this->saveCache('recommend', $deals->take(20)->values());

        $this->saveCache('aaa', $this->popularAAA());
    }

    private function saveCache(string $type, Collection $data): void
    {
        DB::table('game_recommendations')->updateOrInsert(
            ['type' => $type],
            [
                'payload' => $data->values()->toJson(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function genre(string $genre, int $size = 20): Collection
    {
        $json = DB::table('game_recommendations')->where('type', strtolower($genre))->value('payload');

        if (!$json) {
            return collect();
        }

        return collect(json_decode($json, true))->take($size)->values();
    }

    public function cachedRecommend(int $size = 20): Collection
    {
        $json = DB::table('game_recommendations')->where('type', 'recommend')->value('payload');

        if (!$json) {
            return collect();
        }

        return collect(json_decode($json, true))->take($size)->values();
    }

    public function cachedAAA(): Collection
    {
        $json = DB::table('game_recommendations')->where('type', 'aaa')->value('payload');

        if (!$json) {
            return collect();
        }

        return collect(json_decode($json, true))->values();
    }
}
