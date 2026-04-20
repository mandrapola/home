<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pin', 'monitoring')) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->boolean('monitoring')->default(false)->after('show_on_chart');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pin', 'monitoring')) {
            Schema::table('pin', function (Blueprint $table): void {
                $table->dropColumn('monitoring');
            });
        }
    }
};

