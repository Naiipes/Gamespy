<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = [
        "user_id",
        "game_id",
        "target_price"
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}