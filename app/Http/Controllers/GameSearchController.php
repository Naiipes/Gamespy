<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CheapSharkService;
use App\Models\Game;

class GameSearchController extends Controller
{

    public function search(Request $request, CheapSharkService $service)
    {

        $results = $service->search($request->q);

        foreach ($results as $game)
        {

            Game::updateOrCreate(
                ["cheapshark_id"=>$game["gameID"]],
                [
                    "title"=>$game["external"],
                    "thumb"=>$game["thumb"],
                    "cheapest_price"=>$game["cheapest"]
                ]
            );

        }

        return response()->json($results);

    }

}