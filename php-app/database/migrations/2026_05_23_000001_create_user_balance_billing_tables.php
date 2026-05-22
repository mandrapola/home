<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'daily_price_units')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->unsignedInteger('daily_price_units')->default(0)->after('price_amount');
            });

            DB::table('plans')->update([
                'daily_price_units' => DB::raw('FLOOR(price_amount / 31)'),
            ]);
        }

        if (! Schema::hasTable('user_balances')) {
            Schema::create('user_balances', function (Blueprint $table): void {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->integer('balance_units')->default(0);
                $table->timestamp('billing_blocked_at')->nullable();
                $table->string('billing_block_reason', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('balance_transactions')) {
            Schema::create('balance_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 32);
                $table->integer('amount_units');
                $table->unsignedInteger('required_amount_units')->nullable();
                $table->integer('balance_after_units');
                $table->date('billing_date')->nullable();
                $table->string('description')->nullable();
                $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
                $table->timestamps();

                $table->index(['user_id', 'billing_date', 'type'], 'idx_balance_tx_user_date_type');
                $table->index('payment_order_id', 'idx_balance_tx_payment_order');
            });
        }

        if (! Schema::hasTable('user_activity_days')) {
            Schema::create('user_activity_days', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('activity_date');
                $table->timestamp('first_seen_at');
                $table->timestamp('last_seen_at');
                $table->unsignedInteger('reports_count')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'activity_date'], 'uniq_user_activity_day');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_days');
        Schema::dropIfExists('balance_transactions');
        Schema::dropIfExists('user_balances');

        if (Schema::hasColumn('plans', 'daily_price_units')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('daily_price_units');
            });
        }
    }
};
