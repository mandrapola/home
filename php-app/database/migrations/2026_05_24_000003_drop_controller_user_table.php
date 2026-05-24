<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('controller_user');
    }

    public function down(): void
    {
        Schema::create('controller_user', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('controller_id', 36);
            $table->foreignId('user_id');
            $table->string('role', 16)->default('owner');
            $table->timestamps();

            $table->unique(['controller_id', 'user_id'], 'uk_controller_user');
            $table->index(['user_id', 'role'], 'idx_controller_user_role');
            $table->foreign('controller_id')->references('id')->on('controller')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
