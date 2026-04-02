<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Mail;
use Throwable;
use App\Models\Wishlist;
use App\Models\Notification;
use App\Mail\PriceDropMail;

class PriceCheckerService
{

    public function checkPrices()
    {
        $wishlists = Wishlist::with(["game","user"])->get();

        foreach ($wishlists as $wishlist)
        {
            $game = $wishlist->game;

            if (!$game || !$game->cheapshark_id) {
                continue;
            }

            try {
                $response = CheapSharkService::client()->get(
                    "https://www.cheapshark.com/api/1.0/games",
                    ["id" => $game->cheapshark_id]
                );

                $data = $response->json();
            } catch (ConnectionException $e) {
                logger()->warning('Price check skipped because CheapShark request failed.', [
                    'game_id' => $game->id,
                    'title' => $game->title,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!$data)
            {
                continue;
            }

            $bestDeal = CheapSharkService::selectPreferredDeal($data['deals'] ?? []);

            if (!$bestDeal) {
                $wishlist->was_on_sale_last_check = false;
                $wishlist->target_notification_sent = false;
                $wishlist->save();
                continue;
            }

            $currentPrice = isset($bestDeal["price"]) ? (float) $bestDeal["price"] : null;
            $retailPrice = isset($bestDeal["retailPrice"])
                ? (float) $bestDeal["retailPrice"]
                : $currentPrice;

            if ($currentPrice === null) {
                logger()->warning('Price check skipped because CheapShark game deal data was incomplete.', [
                    'game_id' => $game->id,
                    'title' => $game->title,
                    'payload' => $bestDeal,
                ]);
                continue;
            }

            $isOnSale = $retailPrice > $currentPrice;
            $previousSaleState = $wishlist->was_on_sale_last_check;
            $targetPrice = (float) $wishlist->target_price;
            $useTargetPrice = (bool) $wishlist->use_target_price;
            $targetHit = $useTargetPrice && $currentPrice <= $targetPrice;
            $storeName = CheapSharkService::resolveStoreName($bestDeal['storeID'] ?? null);

            $this->syncUnreadNotificationStore($wishlist, $storeName);

            if (!$wishlist->notifications_enabled) {
                $wishlist->was_on_sale_last_check = $isOnSale;
                $wishlist->target_notification_sent = false;
                $wishlist->save();
                continue;
            }

            if ($useTargetPrice) {
                if ($targetHit && !$wishlist->target_notification_sent) {
                    $this->createNotification($wishlist, $currentPrice, $storeName, 'target_price');
                    $wishlist->target_notification_sent = true;
                } elseif (!$targetHit) {
                    $wishlist->target_notification_sent = false;
                }
            } else {
                if ($previousSaleState === false && $isOnSale) {
                    $this->createNotification($wishlist, $currentPrice, $storeName, 'sale');
                }

                $wishlist->target_notification_sent = false;
            }

            $wishlist->was_on_sale_last_check = $isOnSale;
            $wishlist->save();
        }

    }

    private function createNotification(
        Wishlist $wishlist,
        float $currentPrice,
        string $storeName,
        string $type
    ): void {
        $game = $wishlist->game;
        $targetPrice = $type === 'target_price' ? (float) $wishlist->target_price : null;

        Notification::create([
            "user_id" => $wishlist->user_id,
            "game_id" => $game->id,
            "type" => $type,
            "price" => $currentPrice,
            "target_price" => $targetPrice,
            "store" => $storeName,
            "is_read" => false,
        ]);

        if ($wishlist->notify_by_email) {
            try {
                Mail::to($wishlist->user->email)
                    ->send(new PriceDropMail(
                        $game->title,
                        $currentPrice,
                        $targetPrice ?? 0,
                        $storeName,
                        $type
                    ));
            } catch (Throwable $e) {
                logger()->warning('Price notification email failed to send.', [
                    'wishlist_id' => $wishlist->id,
                    'user_id' => $wishlist->user_id,
                    'recipient' => $wishlist->user?->email,
                    'game_id' => $game->id,
                    'game_title' => $game->title,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function syncUnreadNotificationStore(Wishlist $wishlist, string $storeName): void
    {
        $gameId = $wishlist->game?->id;

        if (!$gameId) {
            return;
        }

        Notification::where('user_id', $wishlist->user_id)
            ->where('game_id', $gameId)
            ->where('is_read', false)
            ->update(['store' => $storeName]);
    }
}
