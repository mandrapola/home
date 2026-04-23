<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('alice_oauth_auth_codes')) {
            Schema::create('alice_oauth_auth_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('client_id', 191);
                $table->string('redirect_uri', 2048);
                $table->string('scope', 512)->nullable();
                $table->string('code_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'expires_at'], 'idx_alice_oauth_codes_client_expires');
            });
        }

        if (! Schema::hasTable('alice_oauth_access_tokens')) {
            Schema::create('alice_oauth_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('client_id', 191);
                $table->string('scope', 512)->nullable();
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'revoked_at'], 'idx_alice_oauth_tokens_user_revoked');
                $table->index(['client_id', 'revoked_at'], 'idx_alice_oauth_tokens_client_revoked');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alice_oauth_access_tokens');
        Schema::dropIfExists('alice_oauth_auth_codes');
    }
};
