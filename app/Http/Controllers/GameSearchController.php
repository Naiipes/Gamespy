<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CheapSharkService;
use App\Models\Game;

class GameSearchController extends Controller
{

    public function search(Request $request, CheapSharkService $service)
    {

        $query = $request->q;

        if(!$query){
            return response()->json([]);
        }

        $results = $service->search($query);

        $games = [];

        foreach ($results as $game)
        {

            $record = Game::updateOrCreate(
                ["cheapshark_id"=>$game["gameID"]],
                [
                    "title"=>$game["external"],
                    "thumb"=>$game["thumb"],
                    "cheapest_price"=>$game["cheapest"]
                ]
            );

            $games[] = $record;

        }

        return response()->json(
            collect($games)->map(function($game){
                 return [
                "id"=>$game->id,
                "title"=>$game->title,
                "thumb"=>$game->thumb,
                "price"=>$game->cheapest_price
                ];
        })
        );

    }

}