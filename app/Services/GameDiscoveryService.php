<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GameDiscoveryService
{
    private $cachedDeals = null;

    public function recommend($size = 60)
    {
        if ($this->cachedDeals !== null) {
            return $this->cachedDeals->take($size)->values();
        }

        $page = 0;
        $unique = collect();

        while ($unique->count() < $size) {

            $deals = Http::get(
                "https://www.cheapshark.com/api/1.0/deals",
                [
                    "sortBy"     => "DealRating",
                    "pageSize"   => 30,
                    "pageNumber" => $page,
                ]
            )->json();

            if (empty($deals)) {
                break;
            }

            $unique = $unique
                ->merge($deals)
                ->unique('gameName')
                ->values();

            $page++;

            if ($page > 20) break;
        }

        $this->cachedDeals = $unique->take($size)->values();

        return $this->cachedDeals;
    }

    public function popularAAA()
    {
        $deals = $this->recommend(60);

        $aaa = $deals->filter(function ($deal) {
            return
                ($deal['normalPrice'] ?? 0) >= 39.99 &&
                ($deal['steamRatingCount'] ?? 0) >= 500 &&
                ($deal['metacriticScore'] ?? 0) >= 75;
        });

        return $aaa
            ->sortByDesc('savings')
            ->sortByDesc('dealRating')
            ->sortBy('salePrice')
            ->values()
            ->take(5);
    }
}
