<?php

namespace App\Http\Controllers;

use App\Services\GameDiscoveryService;

class HomeController extends Controller
{
    public function index(GameDiscoveryService $service)
    {
        $popularGames = $service->cachedRecommend();
        $catalogGames = $service->cachedRecommend();
        return view('home', compact('popularGames'), compact('catalogGames'));
    }
}