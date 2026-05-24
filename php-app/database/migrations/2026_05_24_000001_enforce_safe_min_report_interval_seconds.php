<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'min_report_interval_seconds')) {
            return;
        }

        DB::table('plans')
            ->where('min_report_interval_seconds', '<', 5)
            ->update(['min_report_interval_seconds' => 5]);

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('min_report_interval_seconds')->default(5)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'min_report_interval_seconds')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('min_report_interval_seconds')->default(0)->change();
        });
    }
};
