<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controller_registration_attempts', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('device_uid', 64);
            $table->char('provisioning_token_hash', 64);
            $table->string('code', 4);
            $table->string('status', 32)->default('pending');
            $table->char('registered_controller_id', 36)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['device_uid', 'status', 'expires_at'], 'idx_registration_device_active');
            $table->index(['code', 'status', 'expires_at'], 'idx_registration_code_active');
            $table->index('registered_controller_id', 'idx_registration_controller');
            $table->foreign('registered_controller_id', 'fk_reg_attempt_controller')
                ->references('id')
                ->on('controller')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controller_registration_attempts');
    }
};
