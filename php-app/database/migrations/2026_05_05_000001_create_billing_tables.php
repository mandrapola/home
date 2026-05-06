<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name', 128);
                $table->decimal('price_amount', 10, 2)->default(0);
                $table->string('price_currency', 3)->default('RUB');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('max_controllers')->nullable();
                $table->unsignedBigInteger('max_pin_data_rows')->nullable();
                $table->boolean('alice_enabled')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_subscriptions')) {
            Schema::create('user_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->string('source', 32)->default('system_default');
                $table->timestamps();

                $table->index(['user_id', 'status'], 'idx_user_subscriptions_user_status');
                $table->index(['user_id', 'starts_at', 'ends_at'], 'idx_user_subscriptions_user_dates');
            });
        }

        if (!Schema::hasTable('payment_orders')) {
            Schema::create('payment_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('RUB');
                $table->string('status', 16)->default('pending');
                $table->string('provider', 32)->default('yookassa');
                $table->string('provider_payment_id', 128)->nullable()->index();
                $table->string('idempotence_key', 128)->nullable()->unique();
                $table->timestamp('paid_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'idx_payment_orders_user_status');
                $table->index(['plan_id', 'status'], 'idx_payment_orders_plan_status');
            });
        }

        if (!Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 32)->default('yookassa');
                $table->string('provider_event_id', 191)->unique();
                $table->json('payload');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['provider', 'processed_at'], 'idx_payment_webhook_events_provider_processed');
            });
        }

        if (!Schema::hasColumn('users', 'selected_plan_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('selected_plan_id')->nullable()->after('alice_enabled')->constrained('plans')->nullOnDelete();
            });
        }

        $defaultPlanCode = (string) config('smarthome.default_plan', 'free');
        $defaultPlanExists = DB::table('plans')->where('code', $defaultPlanCode)->exists();
        if (!$defaultPlanExists) {
            DB::table('plans')->insert([
                'code' => $defaultPlanCode,
                'name' => strtoupper($defaultPlanCode),
                'description' => 'Default free plan',
                'price_amount' => 0,
                'price_currency' => 'RUB',
                'max_controllers' => 1,
                'max_pin_data_rows' => 10000,
                'alice_enabled' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive for billing baseline migration.
    }
};
