<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'report_interval_seconds')) {
                $table->unsignedInteger('report_interval_seconds')->default(0)->after('daily_price_units');
            }
            if (! Schema::hasColumn('plans', 'max_scenarios')) {
                $table->unsignedInteger('max_scenarios')->nullable()->after('max_pin_data_rows');
            }
            if (! Schema::hasColumn('plans', 'max_scenario_conditions')) {
                $table->unsignedInteger('max_scenario_conditions')->nullable()->after('max_scenarios');
            }
        });

        if (! Schema::hasTable('user_report_limits')) {
            Schema::create('user_report_limits', function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_report_accepted_at')->nullable();
                $table->timestamps();
            });
        }

        DB::table('plans')
            ->whereNull('report_interval_seconds')
            ->update(['report_interval_seconds' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_report_limits');

        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'max_scenario_conditions')) {
                $table->dropColumn('max_scenario_conditions');
            }
            if (Schema::hasColumn('plans', 'max_scenarios')) {
                $table->dropColumn('max_scenarios');
            }
            if (Schema::hasColumn('plans', 'report_interval_seconds')) {
                $table->dropColumn('report_interval_seconds');
            }
        });
    }
};
