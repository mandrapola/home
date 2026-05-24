<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('controller', 'user_id')) {
            Schema::table('controller', function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('controller_user')) {
            return;
        }

        DB::table('controller as c')
            ->join('controller_user as cu', function ($join): void {
                $join->on('cu.controller_id', '=', 'c.id')
                    ->where('cu.role', '=', 'owner');
            })
            ->whereNull('c.user_id')
            ->update(['c.user_id' => DB::raw('cu.user_id')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('controller', 'user_id')) {
            return;
        }

        Schema::table('controller', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
