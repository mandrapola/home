<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('controller', function (Blueprint $table): void {
            $table->boolean('is_service')->default(false)->after('status');
            $table->index('is_service', 'idx_controller_is_service');
        });

        DB::table('controller')
            ->where('id', '0195f7e0-0000-7000-8000-000000000001')
            ->update([
                'status' => 'virtual',
                'is_service' => 1,
            ]);

        DB::table('controller')
            ->where('status', 'virtual')
            ->where('name', 'Алиса')
            ->update(['is_service' => 1]);
    }

    public function down(): void
    {
        Schema::table('controller', function (Blueprint $table): void {
            $table->dropIndex('idx_controller_is_service');
            $table->dropColumn('is_service');
        });
    }
};
