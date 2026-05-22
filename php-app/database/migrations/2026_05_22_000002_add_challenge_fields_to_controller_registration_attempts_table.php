<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controller_registration_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('requested_user_id')->nullable()->after('registered_controller_id');
            $table->string('challenge_code', 4)->nullable()->after('code');
            $table->string('registration_token_hash', 64)->nullable()->after('challenge_code');
            $table->timestamp('challenge_started_at')->nullable()->after('last_seen_at');

            $table->index(['requested_user_id', 'status', 'expires_at'], 'idx_registration_user_status');
            $table->index(['registration_token_hash', 'status'], 'idx_registration_token_status');
            $table->foreign('requested_user_id', 'fk_reg_attempt_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('controller_registration_attempts', function (Blueprint $table): void {
            $table->dropForeign('fk_reg_attempt_user');
            $table->dropIndex('idx_registration_user_status');
            $table->dropIndex('idx_registration_token_status');
            $table->dropColumn([
                'requested_user_id',
                'challenge_code',
                'registration_token_hash',
                'challenge_started_at',
            ]);
        });
    }
};
