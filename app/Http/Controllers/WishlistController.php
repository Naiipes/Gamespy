<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    public function store(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$request->has('game_id') && $request->has('cheapshark_id')) {
            $game = \App\Models\Game::updateOrCreate(
                ['cheapshark_id' => $request->cheapshark_id],
                [
                    'title'          => $request->input('title', 'Unknown'),
                    'thumb'          => $request->input('thumb', ''),
                    'cheapest_price' => $request->input('target_price', 0),
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
            "target_price" => $validated['target_price']
        ]);

        return response()->json(['game_id' => $validated['game_id']], 201);
    }

    public function index()
    {
        if (!auth()->check()) {
            if (request()->wantsJson()) {
                return response()->json([]);
            }
            return view('wishlist', ['wishlists' => []]);
        }

        $wishlists = auth()
            ->user()
            ->wishlists()
            ->with("game")
            ->get();

        if (request()->wantsJson()) {
            return response()->json($wishlists);
        }

        return view('wishlist', compact('wishlists'));
    }

    public function gameIds()
    {
        if (!auth()->check()) {
            return response()->json([]);
        }

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

}