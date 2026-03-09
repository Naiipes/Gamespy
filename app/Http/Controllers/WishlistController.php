<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{

    public function store(Request $request)
    {
        // 容量制限: 10個まで
        $currentCount = auth()->user()->wishlists()->count();
        if ($currentCount >= 10) {
            return response()->json([
                "message" => "Wishlist is full. Maximum 10 items allowed."
            ], 400);
        }

        Wishlist::create([
            "user_id" => auth()->id(),
            "game_id" => $request->game_id,
            "target_price" => $request->target_price
        ]);

        return response()->json([
            "message" => "added"
        ]);
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
        // ユーザーのwishlistから特定のgame_idのアイテムを削除
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