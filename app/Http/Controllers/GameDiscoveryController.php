<?php

namespace App\Http\Controllers;

use App\Services\GameDiscoveryService;

class GameDiscoveryController extends Controller
{

    public function popular(GameDiscoveryService $service)
    {
        return response()->json(
            $service->popular()
        );
    }

    public function discounts(GameDiscoveryService $service)
    {
        return response()->json(
            $service->discounts()
        );
    }

    public function free(GameDiscoveryService $service)
    {
        return response()->json(
            $service->free()
        );
    }

}