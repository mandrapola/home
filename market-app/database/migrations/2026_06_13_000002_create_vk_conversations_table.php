<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_item_id')->nullable()->constrained('market_items')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context_type', 32);
            $table->string('context_token')->nullable()->index();
            $table->bigInteger('vk_user_id')->index();
            $table->string('intent', 64)->nullable();
            $table->string('status', 32)->default('open');
            $table->json('context_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_conversations');
    }
};
