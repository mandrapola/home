<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vk_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 32);
            $table->text('body')->nullable();
            $table->bigInteger('vk_message_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_messages');
    }
};
