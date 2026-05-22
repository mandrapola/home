<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBalance extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'balance_units',
        'billing_blocked_at',
        'billing_block_reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'balance_units' => 'integer',
            'billing_blocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
