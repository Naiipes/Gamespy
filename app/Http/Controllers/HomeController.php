<?php

namespace App\Http\Controllers;

use App\Services\GameDiscoveryService;

class HomeController extends Controller
{
    // Loads cached homepage sections (AAA carousel and recommendation catalog) and renders home view.
    public function index(GameDiscoveryService $service)
    {
        // Home page reads from prebuilt cache tables to avoid live upstream calls.
        $popularGames = $service->cachedAAA();
        $catalogGames = $service->cachedRecommend();
        return view('home', compact('popularGames'), compact('catalogGames'));
    }
}