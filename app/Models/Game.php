<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        "cheapshark_id",
        "title",
        "thumb",
        "cheapest_price"
    ];

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
}