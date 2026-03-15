<?php

namespace App\Http\Controllers;

use App\Services\GameDiscoveryService;

class GameDiscoveryController extends Controller
{
    public function popular(GameDiscoveryService $service)
    {
        return response()->json(
            $service->cachedRecommend()
        );
    }

    public function discounts(GameDiscoveryService $service)
    {
        return response()->json([]);
    }

    public function free(GameDiscoveryService $service)
    {
        return response()->json([]);
    }

    public function genre(string $genre, GameDiscoveryService $service)
    {
        return response()->json(
            $service->genre($genre)
        );
    }

    public function aaa(GameDiscoveryService $service)
    {
        return response()->json(
            $service->cachedAAA()
        );
    }

    public function recommend(GameDiscoveryService $service)
{
    return response()->json(
        $service->cachedRecommend()
    );
}

}
