<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Report\PinDataHistoryService;
use App\Services\Billing\PlanLimitService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class PinDataHistoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('pin_data');
        Schema::dropIfExists('pin');

        Schema::create('pin', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
        });

        Schema::create('pin_data', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('pin_id', 36);
            $table->double('value');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pin_data');
        Schema::dropIfExists('pin');

        parent::tearDown();
    }

    public function test_creates_new_row_inside_interval_window(): void
    {
        Carbon::setTestNow('2026-04-18 12:00:00');

        $pinId = Uuid::uuid7()->toString();
        DB::table('pin')->insert([
            'id' => $pinId,
        ]);

        DB::table('pin_data')->insert([
            'id' => Uuid::uuid7()->toString(),
            'pin_id' => $pinId,
            'value' => 10.0,
            'created_at' => Carbon::now()->subMinutes(2),
        ]);

        $service = app(PinDataHistoryService::class);
        $service->storeReadings(
            [['pin' => 'air_temperature', 'value' => 14.0]],
            ['AIR_TEMPERATURE' => $pinId],
            ['AIR_TEMPERATURE' => 'sensor_temperature']
        );

        $this->assertSame(2, DB::table('pin_data')->count());
        $value = (float) DB::table('pin_data')
            ->where('pin_id', $pinId)
            ->orderByDesc('created_at')
            ->value('value');
        $this->assertSame(14.0, $value);

        Carbon::setTestNow();
    }

    public function test_creates_new_row_when_interval_window_expired(): void
    {
        Carbon::setTestNow('2026-04-18 12:00:00');

        $pinId = Uuid::uuid7()->toString();
        DB::table('pin')->insert([
            'id' => $pinId,
        ]);

        DB::table('pin_data')->insert([
            'id' => Uuid::uuid7()->toString(),
            'pin_id' => $pinId,
            'value' => 10.0,
            'created_at' => Carbon::now()->subMinutes(6),
        ]);

        $service = app(PinDataHistoryService::class);
        $service->storeReadings(
            [['pin' => 'air_temperature', 'value' => 14.0]],
            ['AIR_TEMPERATURE' => $pinId],
            ['AIR_TEMPERATURE' => 'sensor_temperature']
        );

        $this->assertSame(2, DB::table('pin_data')->count());

        Carbon::setTestNow();
    }

    public function test_skips_invalid_power_values(): void
    {
        $pinId = Uuid::uuid7()->toString();
        DB::table('pin')->insert([
            'id' => $pinId,
        ]);

        $service = app(PinDataHistoryService::class);
        $service->storeReadings(
            [
                ['pin' => 'relay_1', 'value' => 2],
                ['pin' => 'relay_1', 'value' => 'on'],
            ],
            ['RELAY_1' => $pinId],
            ['RELAY_1' => 'power']
        );

        $this->assertSame(0, DB::table('pin_data')->count());
    }

    public function test_skips_non_numeric_sensor_values(): void
    {
        $pinId = Uuid::uuid7()->toString();
        DB::table('pin')->insert([
            'id' => $pinId,
        ]);

        $service = app(PinDataHistoryService::class);
        $service->storeReadings(
            [
                ['pin' => 'air_temperature', 'value' => '23.4'],
                ['pin' => 'air_temperature', 'value' => '23,4'],
            ],
            ['AIR_TEMPERATURE' => $pinId],
            ['AIR_TEMPERATURE' => 'sensor_temperature']
        );

        $this->assertSame(1, DB::table('pin_data')->count());
        $this->assertSame(23.4, (float) DB::table('pin_data')->where('pin_id', $pinId)->value('value'));
    }

    public function test_checks_controller_pin_data_limit_once_before_loop(): void
    {
        $controllerId = Uuid::uuid7()->toString();
        $pinId = Uuid::uuid7()->toString();
        DB::table('pin')->insert([
            'id' => $pinId,
        ]);

        $planLimitService = Mockery::mock(PlanLimitService::class);
        $planLimitService
            ->shouldReceive('canInsertPinDataForController')
            ->once()
            ->with($controllerId)
            ->andReturn(true);

        $service = new PinDataHistoryService($planLimitService);
        $service->storeReadings(
            [
                ['pin' => 'air_temperature', 'value' => 23.4],
                ['pin' => 'air_temperature', 'value' => 23.5],
            ],
            ['AIR_TEMPERATURE' => $pinId],
            ['AIR_TEMPERATURE' => 'sensor_temperature'],
            $controllerId
        );

        $this->assertSame(2, DB::table('pin_data')->count());
    }
}
