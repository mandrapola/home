<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pin', function (Blueprint $table): void {
            $table->dropIndex('idx_pin_config_controller_sort');
            $table->dropColumn('sort_order');
            $table->index(['controller_id', 'pin'], 'idx_pin_config_controller_pin');
        });
    }

    public function down(): void
    {
        Schema::table('pin', function (Blueprint $table): void {
            $table->dropIndex('idx_pin_config_controller_pin');
            $table->integer('sort_order')->default(0);
            $table->index(['controller_id', 'sort_order', 'pin'], 'idx_pin_config_controller_sort');
        });
    }
};
