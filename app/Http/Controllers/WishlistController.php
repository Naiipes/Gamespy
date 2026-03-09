<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{

    public function store(Request $request)
    {

        Wishlist::create([
            "user_id"=>auth()->id(),
            "game_id"=>$request->game_id,
            "target_price"=>$request->target_price
        ]);

        return response()->json([
            "message"=>"added"
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

}