<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pin', function (Blueprint $table): void {
            $table->timestamp('last_on_command_sent_at')->nullable()->after('desired_digital_updated_at');
        });

        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.digital_style,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.last_on_command_sent_at,
                   p.show_on_chart,
                   p.show_on_report,
                   p.is_monitored,
                   p.external_enabled,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');
    }

    public function down(): void
    {
        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.digital_style,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.show_on_chart,
                   p.show_on_report,
                   p.is_monitored,
                   p.external_enabled,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');

        Schema::table('pin', function (Blueprint $table): void {
            $table->dropColumn('last_on_command_sent_at');
        });
    }
};
