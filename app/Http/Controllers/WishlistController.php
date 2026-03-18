<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'target_price' => ['required', 'numeric', 'min:0'],
        ]);

        $alreadyExists = Wishlist::where('user_id', auth()->id())
            ->where('game_id', $validated['game_id'])
            ->exists();

        if ($alreadyExists) {
            return response()->noContent(409);
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

        return response()->noContent(201);
    }

    public function index()
    {

        return auth()
            ->user()
            ->wishlists()
            ->with("game")
            ->get();

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