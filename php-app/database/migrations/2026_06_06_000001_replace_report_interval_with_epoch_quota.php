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
            if (! Schema::hasColumn('plans', 'report_epoch_seconds')) {
                $table->unsignedInteger('report_epoch_seconds')->default(300)->after('daily_price_units');
            }
            if (! Schema::hasColumn('plans', 'report_max_requests_per_epoch')) {
                $table->unsignedInteger('report_max_requests_per_epoch')->default(0)->after('report_epoch_seconds');
            }
        });

        DB::table('plans')->whereNull('report_epoch_seconds')->orWhere('report_epoch_seconds', 0)->update([
            'report_epoch_seconds' => 300,
        ]);
        DB::table('plans')->whereNull('report_max_requests_per_epoch')->update([
            'report_max_requests_per_epoch' => 0,
        ]);

        Schema::dropIfExists('user_report_limits');

        if (! Schema::hasTable('user_report_rate_counters')) {
            Schema::create('user_report_rate_counters', function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->timestamp('epoch_ends_at');
                $table->unsignedInteger('requests_count')->default(0);
            });
        }

        if (Schema::hasColumn('plans', 'min_report_interval_seconds')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('min_report_interval_seconds');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_report_rate_counters');

        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'min_report_interval_seconds')) {
                $table->unsignedInteger('min_report_interval_seconds')->default(5)->after('daily_price_units');
            }
        });

        if (! Schema::hasTable('user_report_limits')) {
            Schema::create('user_report_limits', function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_report_accepted_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'report_max_requests_per_epoch')) {
                $table->dropColumn('report_max_requests_per_epoch');
            }
            if (Schema::hasColumn('plans', 'report_epoch_seconds')) {
                $table->dropColumn('report_epoch_seconds');
            }
        });
    }
};
