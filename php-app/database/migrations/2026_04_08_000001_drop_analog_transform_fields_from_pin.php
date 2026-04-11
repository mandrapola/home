<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS controller_pin_config');

        Schema::table('pin', function (Blueprint $table): void {
            if (Schema::hasColumn('pin', 'multiplier')) {
                $table->dropColumn('multiplier');
            }
            if (Schema::hasColumn('pin', 'value_offset')) {
                $table->dropColumn('value_offset');
            }
            if (Schema::hasColumn('pin', 'precision_value')) {
                $table->dropColumn('precision_value');
            }
        });

        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.average_interval_minutes,
                   p.digital_style,
                   p.invert_digital_logic,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.power_on_duration_seconds,
                   p.show_on_chart,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS controller_pin_config');

        Schema::table('pin', function (Blueprint $table): void {
            if (! Schema::hasColumn('pin', 'multiplier')) {
                $table->double('multiplier')->default(1)->after('unit');
            }
            if (! Schema::hasColumn('pin', 'value_offset')) {
                $table->double('value_offset')->default(0)->after('multiplier');
            }
            if (! Schema::hasColumn('pin', 'precision_value')) {
                $table->integer('precision_value')->default(0)->after('value_offset');
            }
        });

        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.multiplier,
                   p.value_offset,
                   p.precision_value,
                   p.average_interval_minutes,
                   p.digital_style,
                   p.invert_digital_logic,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.power_on_duration_seconds,
                   p.show_on_chart,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');
    }
};
