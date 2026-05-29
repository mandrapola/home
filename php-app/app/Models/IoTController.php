<?php

declare(strict_types=1);

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IoTController extends Model
{
    use CrudTrait;

    public const MIN_INTERVAL_SECONDS = 5;
    public const MAX_INTERVAL_SECONDS = 86400;

    protected $table = 'controller';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = [
        'api_token_hash',
        'pending_api_token',
    ];

    protected $fillable = [
        'id',
        'user_id',
        'device_uid',
        'api_token_hash',
        'pending_api_token',
        'api_token_generated_at',
        'api_token_rotated_at',
        'name',
        'discription',
        'send_interval_seconds',
        'status',
        'is_service',
        'last_seen_at',
        'claimed_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'claimed_at' => 'datetime',
        'user_id' => 'integer',
        'is_service' => 'boolean',
        'pending_api_token' => 'encrypted',
        'api_token_generated_at' => 'datetime',
        'api_token_rotated_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(ControllerPairing::class, 'controller_id', 'id');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(Pin::class, 'controller_id', 'id');
    }
}
