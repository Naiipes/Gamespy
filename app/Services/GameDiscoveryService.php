<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GameDiscoveryService
{
    private ?Collection $cachedDeals = null;

    public function recommend(int $size = 60): Collection
    {
        if ($this->cachedDeals !== null) {
            return $this->cachedDeals->take($size)->values();
        }

        $page = 0;
        $unique = collect();

        while ($unique->count() < $size) {
            $deals = Http::get('https://www.cheapshark.com/api/1.0/deals', [
                'sortBy' => 'DealRating',
                'pageSize' => 30,
                'pageNumber' => $page,
            ])->json();

            if (empty($deals)) {
                break;
            }

            $unique = $unique->merge($deals)->unique('gameName')->values();

            $page++;

            if ($page > 20) {
                break;
            }
        }

        $this->cachedDeals = $unique->take($size)->values();

        return $this->cachedDeals;
    }

    public function popularAAA(): Collection
    {
        $deals = $this->recommend(60);

        $aaa = $deals->filter(function ($deal) {
            return ($deal['normalPrice'] ?? 0) >= 39.99 && ($deal['steamRatingCount'] ?? 0) >= 500 && ($deal['metacriticScore'] ?? 0) >= 75;
        });

        return $aaa->sortByDesc('savings')->sortByDesc('dealRating')->sortBy('salePrice')->values()->take(5);
    }

    private function fetchSteamGenres(Collection $deals): array
    {
        $appIds = $deals->pluck('steamAppID')->filter()->unique()->values();

        $result = [];

        foreach ($appIds as $id) {
            $res = Http::get('https://store.steampowered.com/api/appdetails', [
                'appids' => $id,
            ])->json();

            if (!empty($res[$id]['success']) && !empty($res[$id]['data']['genres'])) {
                $result[$id] = collect($res[$id]['data']['genres'])
                    ->pluck('description')
                    ->map(fn($g) => strtolower($g))
                    ->values()
                    ->all();
            }
        }

        return $result;
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
        $deals = $this->recommend(200);

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
