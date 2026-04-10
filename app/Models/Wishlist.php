<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    // Writable wishlist preferences and state flags.
    protected $fillable = [
        "user_id",
        "game_id",
        "target_price",
        "notifications_enabled",
        "notify_by_email",
        "use_target_price",
        "was_on_sale_last_check",
        "target_notification_sent"
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'notify_by_email' => 'boolean',
        'use_target_price' => 'boolean',
        'was_on_sale_last_check' => 'boolean',
        'target_notification_sent' => 'boolean',
    ];

    public function game()
    {
        // Target game for this wishlist entry.
        return $this->belongsTo(Game::class);
    }

    public function user()
    {
        // Owner user for this wishlist item.
        return $this->belongsTo(User::class);
    }
}
