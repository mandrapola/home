<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControllerRegistrationAttempt extends Model
{
    protected $table = 'controller_registration_attempts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_uid',
        'provisioning_token_hash',
        'code',
        'challenge_code',
        'registration_token_hash',
        'status',
        'registered_controller_id',
        'requested_user_id',
        'last_seen_at',
        'challenge_started_at',
        'expires_at',
        'claimed_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'challenge_started_at' => 'datetime',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function registeredController(): BelongsTo
    {
        return $this->belongsTo(IoTController::class, 'registered_controller_id', 'id');
    }

    public function requestedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_user_id', 'id');
    }
}
