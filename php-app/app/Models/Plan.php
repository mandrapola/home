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
        'daily_price_units',
        'report_epoch_seconds',
        'report_max_requests_per_epoch',
        'price_currency',
        'max_pin_data_rows',
        'max_scenarios',
        'max_scenario_conditions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_price_units' => 'integer',
            'report_epoch_seconds' => 'integer',
            'report_max_requests_per_epoch' => 'integer',
            'max_pin_data_rows' => 'integer',
            'max_scenarios' => 'integer',
            'max_scenario_conditions' => 'integer',
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
