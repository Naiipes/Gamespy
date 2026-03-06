<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Wishlist;
use App\Models\Notification;

class PriceCheckerService
{

    public function checkPrices()
    {

        $wishlists = Wishlist::with("game")->get();

        foreach ($wishlists as $wishlist)
        {

            $game = $wishlist->game;

            $response = Http::get(
                "https://www.cheapshark.com/api/1.0/games",
                ["title"=>$game->title]
            );

            $data = $response->json();

            if (!$data)
            {
                continue;
            }

            $currentPrice = $data[0]["cheapest"];

            if ($wishlist->target_price && $currentPrice <= $wishlist->target_price)
            {

                Notification::create([
                    "user_id"=>$wishlist->user_id,
                    "game_id"=>$game->id,
                    "price"=>$currentPrice,
                    "target_price"=>$wishlist->target_price,
                    "is_read"=>false
                ]);

            }

        }

    }

}