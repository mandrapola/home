<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ControllerReportResponseBuilder;
use PHPUnit\Framework\TestCase;

class ControllerReportResponseBuilderTest extends TestCase
{
    public function test_it_builds_response_with_normalized_outputs(): void
    {
        $payload = ControllerReportResponseBuilder::make()
            ->withSendIntervalSeconds(0)
            ->withDigitalOutputs([
                'RELAY_1' => 1,
                'relay_2' => 0,
                ' relay_3 ' => true,
            ])
            ->build();

        $this->assertSame(1, $payload['send_interval_seconds']);
        $this->assertSame([
            'relay_1' => 1,
            'relay_2' => 0,
            'relay_3' => 1,
        ], $payload['digital_outputs']);
        $this->assertArrayHasKey('monitor', $payload);
        $this->assertNull($payload['monitor']);
    }

    public function test_it_includes_api_token_only_when_it_is_set(): void
    {
        $payload = ControllerReportResponseBuilder::make()
            ->withApiToken('individual-token')
            ->build();

        $this->assertSame('individual-token', $payload['api_token']);

        $withoutToken = ControllerReportResponseBuilder::make()
            ->withApiToken(null)
            ->build();

        $this->assertArrayNotHasKey('api_token', $withoutToken);
    }
}
