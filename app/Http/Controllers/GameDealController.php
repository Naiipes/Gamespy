<?php

namespace App\Http\Controllers;

use App\Services\CheapSharkService;

class GameDealController extends Controller
{

    public function show($id, CheapSharkService $service)
    {

        $data = $service->deals($id);

        return response()->json($data);

    }

}