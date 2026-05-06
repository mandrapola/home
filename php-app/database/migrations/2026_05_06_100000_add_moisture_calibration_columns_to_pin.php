<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pin', function (Blueprint $table): void {
            if (! Schema::hasColumn('pin', 'moisture_raw_dry')) {
                $table->double('moisture_raw_dry')->nullable()->after('external_enabled');
            }
            if (! Schema::hasColumn('pin', 'moisture_raw_wet')) {
                $table->double('moisture_raw_wet')->nullable()->after('moisture_raw_dry');
            }
            if (! Schema::hasColumn('pin', 'moisture_show_percent')) {
                $table->boolean('moisture_show_percent')->default(false)->after('moisture_raw_wet');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pin', function (Blueprint $table): void {
            if (Schema::hasColumn('pin', 'moisture_show_percent')) {
                $table->dropColumn('moisture_show_percent');
            }
            if (Schema::hasColumn('pin', 'moisture_raw_wet')) {
                $table->dropColumn('moisture_raw_wet');
            }
            if (Schema::hasColumn('pin', 'moisture_raw_dry')) {
                $table->dropColumn('moisture_raw_dry');
            }
        });
    }
};

