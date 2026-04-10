<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    // Fields written by sale/target-price notification flows.
    protected $fillable = [
        "user_id",
        "game_id",
        "type",
        "price",
        "target_price",
        "store",
        "is_read"
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function game()
    {
        // Notification always references the game it was generated for.
        return $this->belongsTo(Game::class);
    }

    public function user()
    {
        // Notification recipient user.
        return $this->belongsTo(User::class);
    }
}
