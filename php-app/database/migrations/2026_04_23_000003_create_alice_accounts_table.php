<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alice_accounts')) {
            return;
        }

        Schema::create('alice_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('yandex_user_id', 191)->unique();
            $table->timestamp('unlinked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'unlinked_at'], 'idx_alice_accounts_user_unlinked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alice_accounts');
    }
};

