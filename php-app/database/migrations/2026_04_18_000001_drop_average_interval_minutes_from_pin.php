<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS controller_data');
        DB::statement('DROP VIEW IF EXISTS controller_pin_config');

        Schema::table('pin', function (Blueprint $table): void {
            if (Schema::hasColumn('pin', 'average_interval_minutes')) {
                $table->dropColumn('average_interval_minutes');
            }
        });

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

        DB::statement('CREATE OR REPLACE VIEW controller_data AS
            SELECT pd.id,
                   p.pin,
                   pd.value,
                   c.id AS controller_id,
                   pd.created_at
            FROM pin_data pd
            INNER JOIN pin p ON p.id = pd.pin_id
            INNER JOIN controller c ON c.id = p.controller_id');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS controller_data');
        DB::statement('DROP VIEW IF EXISTS controller_pin_config');

        Schema::table('pin', function (Blueprint $table): void {
            if (! Schema::hasColumn('pin', 'average_interval_minutes')) {
                $table->integer('average_interval_minutes')->default(5)->after('unit');
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
                   p.show_on_chart,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');

        DB::statement('CREATE OR REPLACE VIEW controller_data AS
            SELECT pd.id,
                   p.pin,
                   pd.value,
                   c.id AS controller_id,
                   pd.created_at
            FROM pin_data pd
            INNER JOIN pin p ON p.id = pd.pin_id
            INNER JOIN controller c ON c.id = p.controller_id');
    }
};
