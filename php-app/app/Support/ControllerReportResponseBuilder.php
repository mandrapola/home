<?php

declare(strict_types=1);

namespace App\Support;

class ControllerReportResponseBuilder
{
    private int $sendIntervalSeconds = 5;

    /**
     * @var array<string,int>
     */
    private array $digitalOutputs = [];

    private ?string $monitor = null;

    public static function make(): self
    {
        return new self();
    }

    public function withSendIntervalSeconds(int $value): self
    {
        $this->sendIntervalSeconds = max(1, $value);
        return $this;
    }

    /**
     * @param array<string,int|bool|float|string> $outputs
     */
    public function withDigitalOutputs(array $outputs): self
    {
        $normalized = [];
        foreach ($outputs as $pin => $value) {
            $pinKey = strtolower(trim((string) $pin));
            if ($pinKey === '') {
                continue;
            }
            $normalized[$pinKey] = ((int) $value) > 0 ? 1 : 0;
        }

        $this->digitalOutputs = $normalized;
        return $this;
    }

    public function withMonitor(?string $monitor): self
    {
        $value = $monitor !== null ? trim($monitor) : null;
        $this->monitor = $value !== '' ? $value : null;
        return $this;
    }

    /**
     * @return array{
     *   send_interval_seconds:int,
     *   digital_outputs:array<string,int>,
     *   monitor:?string
     * }
     */
    public function build(): array
    {
        return [
            'send_interval_seconds' => $this->sendIntervalSeconds,
            'digital_outputs' => $this->digitalOutputs,
            'monitor' => $this->monitor,
        ];
    }
}
