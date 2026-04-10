<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    // Mass-assignable fields used by search/discovery sync upserts.
    protected $fillable = [
        "cheapshark_id",
        "title",
        "thumb",
        "cheapest_price",
        "steamAppID"
    ];

    public function wishlists()
    {
        // A game can be tracked by many users.
        return $this->hasMany(Wishlist::class);
    }
}