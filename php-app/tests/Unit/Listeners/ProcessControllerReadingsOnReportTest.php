<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\ControllerReadingsReceived;
use App\Listeners\ProcessControllerReadingsOnReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ProcessControllerReadingsOnReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('scenario_condition');
        Schema::dropIfExists('scenario');
        Schema::dropIfExists('pin_power_events');
        Schema::dropIfExists('pin_data');
        Schema::dropIfExists('pin');
        Schema::dropIfExists('controller_pairings');
        Schema::dropIfExists('alice_accounts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('controller');
        Schema::enableForeignKeyConstraints();

        Schema::create('controller', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->text('discription')->nullable();
            $table->integer('send_interval_seconds')->default(5);
            $table->string('status', 16)->default('unclaimed');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('time_zone', 64)->default('Europe/Moscow');
            $table->timestamps();
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
            $table->timestamp('last_on_command_sent_at')->nullable();
            $table->boolean('show_on_chart')->default(false);
            $table->boolean('show_on_report')->default(true);
            $table->boolean('is_monitored')->default(false);
            $table->boolean('external_enabled')->default(true);
            $table->integer('chart_range_hours')->default(1);
            $table->boolean('enable_scenario')->default(true);
        });

        Schema::create('pin_data', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('pin_id', 36);
            $table->double('value');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('scenario', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('pin_id', 36);
            $table->string('name', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('scenario_condition', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('scenario_id', 36);
            $table->char('pin_id', 36);
            $table->string('operator', 8)->default('gt');
            $table->double('threshold');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('scenario_condition');
        Schema::dropIfExists('scenario');
        Schema::dropIfExists('pin_power_events');
        Schema::dropIfExists('pin_data');
        Schema::dropIfExists('pin');
        Schema::dropIfExists('controller_pairings');
        Schema::dropIfExists('alice_accounts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('controller');
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_listener_orchestrates_pin_provisioning_history_value_sync_and_scenarios(): void
    {
        $controllerId = '019d5529-ceee-7748-b9a8-a2e3ce1e8b8f';
        DB::table('controller')->insert([
            'id' => $controllerId,
            'user_id' => null,
            'name' => 'test-controller',
            'send_interval_seconds' => 5,
            'status' => 'unclaimed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $listener = app(ProcessControllerReadingsOnReport::class);

        $listener->handle(new ControllerReadingsReceived($controllerId, [
            ['pin' => 'relay_1', 'value' => 0],
            ['pin' => 'light_level_raw', 'value' => 200],
        ]));

        $relayPin = DB::table('pin')->where('controller_id', $controllerId)->where('pin', 'RELAY_1')->first();
        $lightPin = DB::table('pin')->where('controller_id', $controllerId)->where('pin', 'LIGHT_LEVEL_RAW')->first();

        $this->assertNotNull($relayPin);
        $this->assertNotNull($lightPin);
        $this->assertSame('power', $relayPin->digital_style);
        $this->assertSame('sensor_light', $lightPin->digital_style);

        DB::table('scenario')->insert([
            'id' => Uuid::uuid7()->toString(),
            'pin_id' => $relayPin->id,
            'name' => 'relay on when light > 100',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scenarioId = (string) DB::table('scenario')->where('pin_id', $relayPin->id)->value('id');

        DB::table('scenario_condition')->insert([
            'id' => Uuid::uuid7()->toString(),
            'scenario_id' => $scenarioId,
            'pin_id' => $lightPin->id,
            'operator' => 'gt',
            'threshold' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $listener->handle(new ControllerReadingsReceived($controllerId, [
            ['pin' => 'relay_1', 'value' => 0],
            ['pin' => 'light_level_raw', 'value' => 200],
        ]));

        $relayPinAfter = DB::table('pin')->where('id', $relayPin->id)->first();

        $this->assertNotNull($relayPinAfter);
        $this->assertSame(0.0, (float) $relayPinAfter->value);
        $this->assertSame(1, (int) $relayPinAfter->desired_digital_value);
        $this->assertGreaterThanOrEqual(2, DB::table('pin_data')->count());
    }
}
