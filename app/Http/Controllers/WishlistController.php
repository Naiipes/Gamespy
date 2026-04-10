<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Services\CheapSharkService;

class WishlistController extends Controller
{
    // Validates incoming wishlist request, upserts missing game metadata, and creates one wishlist row.
    public function store(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Allow adding from search cards that only send CheapShark metadata.
        if (!$request->has('game_id') && $request->has('cheapshark_id')) {
            $game = \App\Models\Game::updateOrCreate(
                ['cheapshark_id' => $request->cheapshark_id],
                [
                    'title'          => $request->input('title', 'Unknown'),
                    'thumb'          => $request->input('thumb', ''),
                    'cheapest_price' => $request->input('current_price', 0),
                    'steamAppID'     => $request->input('steamAppID'),
                ]
            );
            $request->merge(['game_id' => $game->id]);
        }

        $validated = $request->validate([
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'target_price' => ['required', 'numeric', 'min:0'],
        ]);

        $alreadyExists = Wishlist::where('user_id', auth()->id())
            ->where('game_id', $validated['game_id'])
            ->exists();

        if ($alreadyExists) {
            return response()->json(['game_id' => $validated['game_id']], 409);
        }

        $currentCount = auth()->user()->wishlists()->count();
        if ($currentCount >= 50) {
            return response()->noContent(422);
        }

        Wishlist::create([
            "user_id" => auth()->id(),
            "game_id" => $validated['game_id'],
            "target_price" => $validated['target_price'],
            "notifications_enabled" => true,
            "notify_by_email" => false,
            "use_target_price" => (float) $validated['target_price'] > 0,
        ]);

        return response()->json(['game_id' => $validated['game_id']], 201);
    }

    public function index(CheapSharkService $service)
    {
        // Returns authenticated user's wishlist with live-enriched deal snapshots and unread notification map.
        if (!auth()->check()) {
            if (request()->wantsJson()) {
                return response()->json([]);
            }
            return view('wishlist', [
                'wishlists' => [],
                'wishlistUnreadNotifications' => collect(),
            ]);
        }

        $user = auth()->user();

        $wishlists = $user
            ->wishlists()
            ->with("game")
            ->get();

        $wishlistUnreadNotifications = $user
            ->wishlistNotifications()
            ->where('is_read', false)
            ->latest()
            ->get()
            ->unique('game_id')
            ->keyBy('game_id');

        $wishlists->each(function ($wishlist) use ($service) {
            $game = $wishlist->game;

            if (!$game || !$game->cheapshark_id) {
                return;
            }

            // Refresh deal snapshot per listed game for up-to-date wishlist pricing.
            $dealData = $service->deals($game->cheapshark_id);
            $bestDeal = CheapSharkService::selectPreferredDeal($dealData['deals'] ?? []);

            if (!$bestDeal) {
                $game->salePrice = $game->cheapest_price;
                $game->normalPrice = null;
                return;
            }

            $game->salePrice = $bestDeal["price"] ?? $game->cheapest_price;
            $game->normalPrice = $bestDeal["retailPrice"] ?? null;
            $game->savings = $bestDeal["savings"] ?? null;
            $game->storeID = $bestDeal["storeID"] ?? null;
            $game->dealID = $bestDeal["dealID"] ?? null;
            $game->cheapest_price = $game->salePrice;
        });

        if (request()->wantsJson()) {
            return response()->json($wishlists);
        }

        return view('wishlist', compact('wishlists', 'wishlistUnreadNotifications'));
    }

    public function gameIds()
    {
        // Returns compact wishlist game identifiers for client-side state sync.
        if (!auth()->check()) {
            return response()->json([]);
        }

        // Lightweight payload used by frontend heart-state synchronization.
        $ids = auth()->user()->wishlists()
            ->with('game:id,cheapshark_id')
            ->get()
            ->map(fn ($w) => [
                'game_id'      => $w->game_id,
                'cheapshark_id'=> $w->game?->cheapshark_id,
            ]);

        return response()->json($ids);
    }

    public function deleteGame(Request $request, $game_id)
    {
        // Deletes one wishlist entry for the authenticated user by game ID.
        $wishlist = Wishlist::where("user_id", auth()->id())
                            ->where("game_id", $game_id)
                            ->first();

        if (!$wishlist) {
            return response()->json([
                'message' => 'Game not found in wishlist'
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'message' => 'Game removed from wishlist'
        ]);
    }

    public function updateTargetPrice(Request $request, $game_id)
    {
        // Updates notification preferences/target price flags for one wishlist game.
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'notifications_enabled' => ['required', 'boolean'],
            'notify_by_email' => ['required', 'boolean'],
            'use_target_price' => ['required', 'boolean'],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $wishlist = Wishlist::where('user_id', auth()->id())
            ->where('game_id', $game_id)
            ->first();

        if (!$wishlist) {
            return response()->json([
                'message' => 'Game not found in wishlist'
            ], 404);
        }

        $notificationsEnabled = (bool) $validated['notifications_enabled'];
        $notifyByEmail = $notificationsEnabled && (bool) $validated['notify_by_email'];
        $useTargetPrice = $notificationsEnabled && (bool) $validated['use_target_price'];
        $targetPrice = $useTargetPrice ? (float) ($validated['target_price'] ?? 0) : 0;

        $wishlist->notifications_enabled = $notificationsEnabled;
        $wishlist->notify_by_email = $notifyByEmail;
        $wishlist->use_target_price = $useTargetPrice;
        $wishlist->target_price = $targetPrice;
        $wishlist->save();

        return response()->json([
            'game_id' => $wishlist->game_id,
            'target_price' => $wishlist->target_price,
            'notifications_enabled' => (bool) $wishlist->notifications_enabled,
            'notify_by_email' => (bool) $wishlist->notify_by_email,
            'use_target_price' => (bool) $wishlist->use_target_price,
            'message' => 'Target price updated'
        ]);
    }

}
