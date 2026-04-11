<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControllerPairing extends Model
{
    protected $table = 'controller_pairings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'controller_id',
        'user_id',
        'code',
        'status',
        'expires_at',
        'displayed_at',
        'claimed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'displayed_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function controller(): BelongsTo
    {
        return $this->belongsTo(IoTController::class, 'controller_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
