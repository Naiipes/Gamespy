<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
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
        return $this->belongsTo(Game::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
