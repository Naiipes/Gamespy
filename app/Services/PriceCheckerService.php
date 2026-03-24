<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Models\Wishlist;
use App\Models\Notification;
use App\Mail\PriceDropMail;

class PriceCheckerService
{

    public function checkPrices()
    {
        $stores = CheapSharkService::getAllStores();

        $wishlists = Wishlist::with(["game","user"])->get();

        foreach ($wishlists as $wishlist)
        {
            if (!$wishlist->notifications_enabled) {
                continue;
            }

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
            $dealID = $data[0]["cheapestDealID"];

            $dealResponse = Http::get(
                "https://www.cheapshark.com/api/1.0/deals",
                ["id"=>$dealID]
            );

            $deal = $dealResponse->json();

            $storeName = $stores[$deal["storeID"]] ?? "Unknown";

            $alreadyNotified = Notification::where('user_id', $wishlist->user_id)
            ->where('game_id', $game->id)
            ->where('is_read', false)
            ->exists();

            $isTargetHit = $wishlist->use_target_price
                ? ($wishlist->target_price && $currentPrice <= $wishlist->target_price)
                : false;

            if ($isTargetHit && !$alreadyNotified)
            {

                Notification::create([
                    "user_id"=>$wishlist->user_id,
                    "game_id"=>$game->id,
                    "price"=>$currentPrice,
                    "target_price"=>$wishlist->target_price,
                    "store"=>$storeName,
                    "is_read"=>false
                ]);

                if ($wishlist->notify_by_email) {
                    Mail::to($wishlist->user->email)
                        ->send(new PriceDropMail(
                            $game->title,
                            $currentPrice,
                            $wishlist->target_price,
                            $storeName
                        ));
                }
            }

        }

    }

}