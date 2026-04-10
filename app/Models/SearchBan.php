<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchBan extends Model
{
    protected $fillable = [
        'ip',
        'user_id',
        'banned_until',
    ];

    protected $casts = [
        'banned_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
