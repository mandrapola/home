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
        'min_report_interval_seconds',
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
            'min_report_interval_seconds' => 'integer',
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
