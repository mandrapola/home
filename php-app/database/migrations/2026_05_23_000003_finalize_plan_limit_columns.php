<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'report_interval_seconds') && ! Schema::hasColumn('plans', 'min_report_interval_seconds')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->renameColumn('report_interval_seconds', 'min_report_interval_seconds');
            });
        }

        foreach (['max_pin_data_rows', 'max_scenarios', 'max_scenario_conditions'] as $column) {
            if (Schema::hasColumn('plans', $column)) {
                DB::table('plans')->whereNull($column)->update([$column => 0]);
            }
        }

        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'daily_price_units')) {
                $table->unsignedInteger('daily_price_units')->default(0)->change();
            }
            if (Schema::hasColumn('plans', 'min_report_interval_seconds')) {
                $table->unsignedInteger('min_report_interval_seconds')->default(0)->change();
            }
            if (Schema::hasColumn('plans', 'max_pin_data_rows')) {
                $table->unsignedBigInteger('max_pin_data_rows')->default(0)->change();
            }
            if (Schema::hasColumn('plans', 'max_scenarios')) {
                $table->unsignedInteger('max_scenarios')->default(0)->change();
            }
            if (Schema::hasColumn('plans', 'max_scenario_conditions')) {
                $table->unsignedInteger('max_scenario_conditions')->default(0)->change();
            }
        });

        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'price_amount')) {
                $table->dropColumn('price_amount');
            }
            if (Schema::hasColumn('plans', 'max_controllers')) {
                $table->dropColumn('max_controllers');
            }
            if (Schema::hasColumn('plans', 'alice_enabled')) {
                $table->dropColumn('alice_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'price_amount')) {
                $table->decimal('price_amount', 10, 2)->default(0)->after('name');
            }
            if (! Schema::hasColumn('plans', 'max_controllers')) {
                $table->unsignedInteger('max_controllers')->nullable()->after('price_currency');
            }
            if (! Schema::hasColumn('plans', 'alice_enabled')) {
                $table->boolean('alice_enabled')->default(false)->after('max_pin_data_rows');
            }
        });

        if (Schema::hasColumn('plans', 'min_report_interval_seconds') && ! Schema::hasColumn('plans', 'report_interval_seconds')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->renameColumn('min_report_interval_seconds', 'report_interval_seconds');
            });
        }
    }
};
