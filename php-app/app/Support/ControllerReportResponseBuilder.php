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
    private ?string $controllerId = null;
    private bool $registrationRequired = false;

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

    public function withControllerId(?string $controllerId): self
    {
        $this->controllerId = $controllerId !== null ? trim($controllerId) : null;
        return $this;
    }

    public function withRegistrationRequired(bool $registrationRequired): self
    {
        $this->registrationRequired = $registrationRequired;
        return $this;
    }

    /**
     * @return array{
     *   send_interval_seconds:int,
     *   digital_outputs:array<string,int>,
     *   monitor:?string,
     *   controller_id?:string,
     *   registration_required?:bool
     * }
     */
    public function build(): array
    {
        $payload = [
            'send_interval_seconds' => $this->sendIntervalSeconds,
            'digital_outputs' => $this->digitalOutputs,
            'monitor' => $this->monitor,
        ];

        if ($this->controllerId !== null) {
            $payload['controller_id'] = $this->controllerId;
        }

        if ($this->registrationRequired) {
            $payload['registration_required'] = true;
        }

        return $payload;
    }
}
