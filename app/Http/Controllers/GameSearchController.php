<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\CheapSharkService;
use App\Models\Game;

class GameSearchController extends Controller
{
    private const SEARCH_CACHE_TTL_MINUTES = 3;

    public function search(Request $request, CheapSharkService $service)
    {
        $query = $request->q;

        if (!$query) {
            if ($request->wantsJson()) {
                return response()->json([]);
            }
            return view('search', ['games' => []]);
        }

        $normalizedQuery = mb_strtolower(preg_replace('/\s+/', ' ', trim($query)));
        $cacheKey = 'search:' . $normalizedQuery;

        $games = Cache::remember($cacheKey, now()->addMinutes(self::SEARCH_CACHE_TTL_MINUTES), function () use ($query, $service) {
            $results = CheapSharkService::normalizeDealList($service->searchDealsAggregated($query));

            if (empty($results)) {
                return [];
            }

            $lowestDeals = collect($results)
                ->groupBy(fn ($game) => $game['gameID'] ?? $game['internalName'] ?? $game['title'])
                ->map(function ($deals) {
                    return CheapSharkService::selectPreferredDeal($deals->all()) ?? $deals->first();
                })
                ->values();

            $games = [];

            foreach ($lowestDeals as $game) {
                $record = Game::updateOrCreate(
                    ['cheapshark_id' => $game['gameID']],
                    [
                        'title' => $game['title'],
                        'thumb' => $game['thumb'],
                        'cheapest_price' => $game['salePrice'],
                        'steamAppID' => $game['steamAppID'] ?? null,
                    ],
                );

                $record->dealID = $game['dealID'] ?? null;
                $record->steamAppID = $game['steamAppID'] ?? null;
                $record->salePrice = $game['salePrice'] ?? null;
                $record->normalPrice = $game['normalPrice'] ?? null;
                $record->savings = $game['savings'] ?? null;
                $record->storeID = $game['storeID'] ?? null;
                $games[] = $record;
            }

            return $games;
        });

        if ($request->wantsJson()) {
            return response()->json(
                collect($games)->map(function ($game) {
                    return [
                        'id' => $game->id,
                        'title' => $game->title,
                        'thumb' => $game->thumb,
                        'price' => $game->cheapest_price,
                    ];
                }),
            );
        }

        return view('search', compact('games'));
    }
}
