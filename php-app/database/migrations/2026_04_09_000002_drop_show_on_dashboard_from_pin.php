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

        if (Schema::hasColumn('pin', 'show_on_dashboard')) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->dropColumn('show_on_dashboard');
            });
        }

        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.digital_style,
                   p.invert_digital_logic,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.show_on_chart,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS controller_pin_config');

        if (! Schema::hasColumn('pin', 'show_on_dashboard')) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->boolean('show_on_dashboard')->default(true)->after('desired_digital_updated_at');
            });
        }

        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.digital_style,
                   p.invert_digital_logic,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.show_on_dashboard,
                   p.show_on_chart,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');
    }
};
