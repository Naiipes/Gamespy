<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = [
        "user_id",
        "game_id",
        "target_price",
        "notifications_enabled",
        "notify_by_email",
        "use_target_price"
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'notify_by_email' => 'boolean',
        'use_target_price' => 'boolean',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}