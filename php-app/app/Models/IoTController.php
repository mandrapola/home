<?php

declare(strict_types=1);

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IoTController extends Model
{
    use CrudTrait;

    public const MIN_INTERVAL_SECONDS = 5;
    public const MAX_INTERVAL_SECONDS = 86400;

    protected $table = 'controller';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'discription',
        'send_interval_seconds',
        'status',
        'last_seen_at',
        'claimed_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'controller_user', 'controller_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
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
