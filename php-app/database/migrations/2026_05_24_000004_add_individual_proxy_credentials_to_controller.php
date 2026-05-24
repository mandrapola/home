<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controller', function (Blueprint $table): void {
            if (! Schema::hasColumn('controller', 'api_token_hash')) {
                $table->char('api_token_hash', 64)->nullable()->unique()->after('user_id');
            }
            if (! Schema::hasColumn('controller', 'pending_api_token')) {
                $table->text('pending_api_token')->nullable()->after('api_token_hash');
            }
            if (! Schema::hasColumn('controller', 'api_token_generated_at')) {
                $table->timestamp('api_token_generated_at')->nullable()->after('pending_api_token');
            }
            if (! Schema::hasColumn('controller', 'api_token_rotated_at')) {
                $table->timestamp('api_token_rotated_at')->nullable()->after('api_token_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('controller', function (Blueprint $table): void {
            if (Schema::hasColumn('controller', 'api_token_hash')) {
                $table->dropUnique(['api_token_hash']);
            }

            $columns = array_filter([
                'api_token_hash',
                'pending_api_token',
                'api_token_generated_at',
                'api_token_rotated_at',
            ], fn (string $column): bool => Schema::hasColumn('controller', $column));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
