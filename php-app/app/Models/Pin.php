<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pin extends Model
{
    use CrudTrait;

    protected $table = 'pin';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'controller_id',
        'pin',
        'label',
        'unit',
        'digital_style',
        'value',
        'value_updated_at',
        'desired_digital_value',
        'desired_digital_updated_at',
        'last_on_command_sent_at',
        'show_on_chart',
        'show_on_report',
        'is_monitored',
        'external_enabled',
        'chart_range_hours',
        'enable_scenario',
        'moisture_raw_dry',
        'moisture_raw_wet',
        'moisture_show_percent',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'value_updated_at' => 'datetime',
            'desired_digital_value' => 'integer',
            'desired_digital_updated_at' => 'datetime',
            'last_on_command_sent_at' => 'datetime',
            'show_on_chart' => 'boolean',
            'show_on_report' => 'boolean',
            'is_monitored' => 'boolean',
            'external_enabled' => 'boolean',
            'chart_range_hours' => 'integer',
            'enable_scenario' => 'boolean',
            'moisture_raw_dry' => 'float',
            'moisture_raw_wet' => 'float',
            'moisture_show_percent' => 'boolean',
        ];
    }

    public function controller(): BelongsTo
    {
        return $this->belongsTo(IoTController::class, 'controller_id', 'id');
    }
}
