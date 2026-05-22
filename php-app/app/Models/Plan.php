<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use CrudTrait;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_amount',
        'daily_price_units',
        'price_currency',
        'max_controllers',
        'max_pin_data_rows',
        'alice_enabled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'daily_price_units' => 'integer',
            'max_controllers' => 'integer',
            'max_pin_data_rows' => 'integer',
            'alice_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserPlanSubscription::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'plan_id');
    }
}
