<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        "user_id",
        "game_id",
        "price",
        "target_price",
        "is_read"
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}