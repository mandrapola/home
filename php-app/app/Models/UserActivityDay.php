<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityDay extends Model
{
    protected $fillable = [
        'user_id',
        'activity_date',
        'first_seen_at',
        'last_seen_at',
        'reports_count',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'activity_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'reports_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
