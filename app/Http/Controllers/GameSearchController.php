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
            if ($request->wantsJson()) {
                return response()->json([]);
            }
            return view('search', ['games' => []]);
        }

        $results = $service->search($query);

        dd($results); // デバッグ: APIのレスポンスを確認

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

        if ($request->wantsJson()) {
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

        return view('search', compact('games'));
    }

}