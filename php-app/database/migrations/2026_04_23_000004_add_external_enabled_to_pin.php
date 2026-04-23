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
        if (! Schema::hasColumn('pin', 'external_enabled')) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->boolean('external_enabled')->default(true)->after('is_monitored');
            });
        }

        DB::table('pin')
            ->whereNull('external_enabled')
            ->orWhere('external_enabled', 0)
            ->update(['external_enabled' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('pin', 'external_enabled')) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->dropColumn('external_enabled');
            });
        }
    }
};
