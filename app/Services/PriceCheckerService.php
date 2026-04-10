<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Throwable;
use App\Models\Wishlist;
use App\Models\Notification;
use App\Models\Game;
use App\Mail\PriceDropMail;

class PriceCheckerService
{
    private const DEFERRED_WISHLIST_GAME_IDS_CACHE_KEY = 'prices:check:deferred-wishlist-game-ids';
    private const DEFERRED_WISHLIST_GAME_IDS_TTL_MINUTES = 40;

    // Fetches current deals per game, updates wishlist state flags, and creates notifications when rules match.
    public function checkPrices()
    {
        // Force-delete stale notification rows older than 2 weeks.
        Notification::where('created_at', '<=', now()->subWeeks(2))->delete();

        $wishlists = Wishlist::with(["game","user"])->get();

        // Group by game_id to avoid duplicate API calls for the same game
        $wishlistsByGame = $wishlists->groupBy(fn($w) => $w->game_id);
        $gameDataCache = [];

        // Only call CheapShark for games that still have at least one notification-enabled wishlist.
        $gamesToCheck = $wishlistsByGame
            ->filter(fn ($group) => $group->contains(fn ($wishlist) => (bool) $wishlist->notifications_enabled))
            ->map(fn ($group) => $group->first()?->game)
            ->filter(fn ($game) => $game && $game->cheapshark_id)
            ->keyBy(fn ($game) => $game->id)
            ->values();

        $deferredGames = $wishlistsByGame
            ->reject(fn ($group) => $group->contains(fn ($wishlist) => (bool) $wishlist->notifications_enabled))
            ->map(fn ($group) => $group->first()?->game)
            ->filter(fn ($game) => $game && $game->cheapshark_id)
            ->keyBy(fn ($game) => $game->id)
            ->values();

        // Keep deferred game IDs so low-priority refresh can run after notification-critical work.
        Cache::put(
            self::DEFERRED_WISHLIST_GAME_IDS_CACHE_KEY,
            $deferredGames->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            now()->addMinutes(self::DEFERRED_WISHLIST_GAME_IDS_TTL_MINUTES),
        );

        foreach ($gamesToCheck as $game) {
            try {
                $response = CheapSharkService::rateLimitedCall(
                    'https://www.cheapshark.com/api/1.0/games',
                    ['id' => $game->cheapshark_id],
                    CheapSharkService::PRIORITY_PRICE_CHECK,
                );

                $gameDataCache[$game->id] = $response->json();
            } catch (ConnectionException $e) {
                logger()->warning('Price check skipped because CheapShark request failed.', [
                    'game_id' => $game->id,
                    'title' => $game->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($wishlistsByGame as $gameId => $gameWishlists) {
            $game = $gameWishlists->first()?->game;

            if (!$game || !isset($gameDataCache[$game->id])) {
                continue;
            }

            $data = $gameDataCache[$game->id];

            if (!$data) {
                continue;
            }

            $bestDeal = CheapSharkService::selectPreferredDeal($data['deals'] ?? []);

            // Process all wishlists for this game with the cached data
            foreach ($gameWishlists as $wishlist) {
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
                    // Track sale state even when notifications are disabled, so reenabling is consistent.
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

        // After notification checks, refresh non-notification wishlist games lazily at lowest API priority.
        $this->refreshDeferredWishlistGamePrices();

    }

    // Refreshes cheapest_price for non-notification wishlist games with low-priority upstream requests.
    private function refreshDeferredWishlistGamePrices(): void
    {
        // Non-notification wishlists are refreshed lazily with lowest request priority.
        $gameIds = Cache::get(self::DEFERRED_WISHLIST_GAME_IDS_CACHE_KEY, []);
        if (!is_array($gameIds) || empty($gameIds)) {
            return;
        }

        $games = Game::query()
            ->whereIn('id', $gameIds)
            ->whereNotNull('cheapshark_id')
            ->get();

        foreach ($games as $game) {
            try {
                $payload = CheapSharkService::rateLimitedCall(
                    'https://www.cheapshark.com/api/1.0/games',
                    ['id' => $game->cheapshark_id],
                    CheapSharkService::PRIORITY_BUILD_CACHE,
                )->json();
            } catch (ConnectionException $e) {
                logger()->warning('Deferred wishlist game price refresh skipped because CheapShark request failed.', [
                    'game_id' => $game->id,
                    'title' => $game->title,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $bestDeal = CheapSharkService::selectPreferredDeal($payload['deals'] ?? []);
            if (!$bestDeal) {
                continue;
            }

            $currentPrice = $bestDeal['price'] ?? $bestDeal['salePrice'] ?? null;
            if ($currentPrice === null) {
                continue;
            }

            $game->cheapest_price = (float) $currentPrice;
            $game->save();
        }
    }

    // Persists one notification row and optionally sends notification email.
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

    // Keeps unread notifications aligned with latest preferred store for the same user/game.
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
