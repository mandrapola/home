<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ReportReading;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportReadingTest extends TestCase
{
    public function test_it_parses_controller_id_and_normalizes_readings(): void
    {
        $request = Request::create('/api/controller/report', 'POST', [
            'controller_id' => '019d5529-ceee-7748-b9a8-a2e3ce1e8b8f',
            'readings' => [
                ['pin' => ' relay_1 ', 'value' => 1],
                ['pin' => 'air_temperature', 'value' => '23.4'],
                ['pin' => 'bad_pin', 'value' => 'abc'],
            ],
        ]);

        $dto = new ReportReading($request);

        $this->assertSame('019d5529-ceee-7748-b9a8-a2e3ce1e8b8f', $dto->getControllerId());
        $this->assertCount(2, $dto->getReadings());
        $this->assertSame('RELAY_1', $dto->getReadings()[0]['pin']);
        $this->assertSame(1.0, $dto->getReadings()[0]['value']);
        $this->assertSame('AIR_TEMPERATURE', $dto->getReadings()[1]['pin']);
    }

    public function test_it_throws_validation_exception_when_controller_id_is_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/api/controller/report', 'POST', [
            'controller_id' => 'not-uuid',
            'readings' => [
                ['pin' => 'relay_1', 'value' => 1],
            ],
        ]);

        new ReportReading($request);
    }
}
