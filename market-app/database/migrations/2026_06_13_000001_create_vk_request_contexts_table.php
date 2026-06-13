<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_request_contexts', function (Blueprint $table): void {
            $table->id();
            $table->string('token')->unique();
            $table->string('type', 32);
            $table->json('payload');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_request_contexts');
    }
};
