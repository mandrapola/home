<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('time_zone', 64)->default('Europe/Moscow');
            $table->string('locale', 8)->default('ru');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('controller', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('discription')->nullable();
            $table->integer('send_interval_seconds')->default(30);
            $table->string('status', 16)->default('unclaimed');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });

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

        Schema::create('controller_pairings', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('controller_id', 36);
            $table->foreignId('user_id');
            $table->string('code', 4);
            $table->string('status', 16)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('displayed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['controller_id', 'status', 'expires_at'], 'idx_pairing_active');
            $table->index(['user_id', 'status'], 'idx_pairing_user_status');
            $table->foreign('controller_id')->references('id')->on('controller')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('pin', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('controller_id', 36);
            $table->string('pin', 64);
            $table->string('label', 255);
            $table->string('unit', 32)->nullable();
            $table->string('digital_style', 32)->default('sensor');
            $table->double('value')->nullable();
            $table->timestamp('value_updated_at')->nullable();
            $table->tinyInteger('desired_digital_value')->nullable();
            $table->timestamp('desired_digital_updated_at')->nullable();
            $table->boolean('show_on_chart')->default(false);
            $table->boolean('show_on_report')->default(true);
            $table->boolean('is_monitored')->default(false);
            $table->integer('chart_range_hours')->default(1);
            $table->boolean('enable_scenario')->default(true);

            $table->unique(['controller_id', 'pin'], 'uk_controller_pin');
            $table->index(['controller_id', 'pin'], 'idx_pin_config_controller_pin');
            $table->foreign('controller_id')->references('id')->on('controller')->cascadeOnDelete();
        });

        Schema::create('pin_data', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('pin_id', 36);
            $table->double('value');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pin_id', 'created_at'], 'idx_pin_data_pin_created');
            $table->index('created_at', 'idx_pin_data_created');
            $table->foreign('pin_id')->references('id')->on('pin')->cascadeOnDelete();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('time_zone', 64)->default('Europe/Moscow');
        });

        Schema::create('scenario', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('pin_id', 36);
            $table->string('name', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['pin_id', 'name'], 'uk_scenario_definition_pin_name');
            $table->foreign('pin_id')->references('id')->on('pin')->cascadeOnDelete();
        });

        Schema::create('scenario_condition', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('scenario_id', 36);
            $table->char('pin_id', 36);
            $table->string('operator', 8)->default('gt');
            $table->double('threshold');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('scenario_id', 'idx_scenarios_definition');
            $table->index('pin_id', 'idx_scenario_condition_pin');
            $table->foreign('pin_id')->references('id')->on('pin')->cascadeOnDelete();
            $table->foreign('scenario_id')->references('id')->on('scenario')->cascadeOnDelete();
        });

        DB::statement('CREATE OR REPLACE VIEW controller_pin_config AS
            SELECT p.id,
                   c.id AS controller_id,
                   p.pin,
                   p.label,
                   p.unit,
                   p.digital_style,
                   p.value,
                   p.value_updated_at,
                   p.desired_digital_value,
                   p.desired_digital_updated_at,
                   p.show_on_chart,
                   p.show_on_report,
                   p.is_monitored,
                   p.chart_range_hours,
                   p.enable_scenario
            FROM pin p
            INNER JOIN controller c ON c.id = p.controller_id');

        DB::statement('CREATE OR REPLACE VIEW controller_data AS
            SELECT pd.id,
                   p.pin,
                   pd.value,
                   c.id AS controller_id,
                   pd.created_at
            FROM pin_data pd
            INNER JOIN pin p ON p.id = pd.pin_id
            INNER JOIN controller c ON c.id = p.controller_id');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS controller_data');
        DB::statement('DROP VIEW IF EXISTS controller_pin_config');

        Schema::dropIfExists('scenario_condition');
        Schema::dropIfExists('scenario');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('pin_data');
        Schema::dropIfExists('pin');
        Schema::dropIfExists('controller_pairings');
        Schema::dropIfExists('controller_user');
        Schema::dropIfExists('controller');

        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
