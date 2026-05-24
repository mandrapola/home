<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ControllerAutoRegistrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ControllerAutoRegistrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('controller_pairings');
        Schema::dropIfExists('pin');
        Schema::dropIfExists('controller');
        Schema::enableForeignKeyConstraints();
        Schema::create('controller', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 255);
            $table->text('discription')->nullable();
            $table->integer('send_interval_seconds')->default(30);
            $table->string('status', 16)->default('unclaimed');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('controller_pairings');
        Schema::dropIfExists('pin');
        Schema::dropIfExists('controller');
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    public function test_it_creates_unclaimed_controller_for_new_id(): void
    {
        $service = app(ControllerAutoRegistrationService::class);
        $controllerId = '019d5529-ceee-7748-b9a8-a2e3ce1e8b8f';
        $ip = '192.168.0.231';

        $service->ensureControllerExists($controllerId, $ip);

        $row = DB::table('controller')->where('id', $controllerId)->first();

        $this->assertNotNull($row);
        $this->assertSame($controllerId, $row->id);
        $this->assertSame('controller-019d5529', $row->name);
        $this->assertSame('unclaimed', $row->status);
        $this->assertSame(5, (int) $row->send_interval_seconds);
        $this->assertStringContainsString($ip, (string) $row->discription);
        $this->assertNotNull($row->last_seen_at);
    }

    public function test_it_does_not_create_duplicate_controller_for_existing_id(): void
    {
        $service = app(ControllerAutoRegistrationService::class);
        $controllerId = '019d5529-ceee-7748-b9a8-a2e3ce1e8b8f';

        $service->ensureControllerExists($controllerId, '192.168.0.231');
        $service->ensureControllerExists($controllerId, '10.0.0.77');

        $count = DB::table('controller')->where('id', $controllerId)->count();
        $row = DB::table('controller')->where('id', $controllerId)->first();

        $this->assertSame(1, $count);
        $this->assertNotNull($row);
        $this->assertStringContainsString('192.168.0.231', (string) $row->discription);
        $this->assertStringNotContainsString('10.0.0.77', (string) $row->discription);
    }
}
