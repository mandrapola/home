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
        $hasMonitoring = Schema::hasColumn('pin', 'monitoring');
        $hasIsMonitored = Schema::hasColumn('pin', 'is_monitored');

        if (! $hasIsMonitored) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->boolean('is_monitored')->default(false)->after('show_on_chart');
            });
            $hasIsMonitored = true;
        }

        if ($hasMonitoring && $hasIsMonitored) {
            DB::statement('UPDATE pin SET is_monitored = monitoring');
            Schema::table('pin', function (Blueprint $table): void {
                $table->dropColumn('monitoring');
            });
        }
    }

    public function down(): void
    {
        $hasMonitoring = Schema::hasColumn('pin', 'monitoring');
        $hasIsMonitored = Schema::hasColumn('pin', 'is_monitored');

        if (! $hasMonitoring) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->boolean('monitoring')->default(false)->after('show_on_chart');
            });
            $hasMonitoring = true;
        }

        if ($hasMonitoring && $hasIsMonitored) {
            DB::statement('UPDATE pin SET monitoring = is_monitored');
            Schema::table('pin', function (Blueprint $table): void {
                $table->dropColumn('is_monitored');
            });
        }
    }
};

