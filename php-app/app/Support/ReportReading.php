<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportReading
{
    private string $controllerId = '';

    /**
     * @var array<int, array{pin:string,value:float}>
     */
    private array $readings = [];

    public function __construct(Request $request)
    {
        $this->hydrateFromRequest($request);
    }

    public function getControllerId(): string
    {
        return $this->controllerId;
    }

    public function setControllerId(string $controllerId): self
    {
        $this->controllerId = trim($controllerId);
        return $this;
    }

    /**
     * @return array<int, array{pin:string,value:float}>
     */
    public function getReadings(): array
    {
        return $this->readings;
    }

    /**
     * @param array<int, array{pin:string,value:float|int|string}> $readings
     */
    public function setReadings(array $readings): self
    {
        $normalized = [];
        foreach ($readings as $reading) {
            $pin = $this->normalizePin((string) ($reading['pin'] ?? ''));
            $value = $this->toNumber($reading['value'] ?? null);
            if ($pin === '' || $value === null) {
                continue;
            }
            $normalized[] = [
                'pin' => $pin,
                'value' => $value,
            ];
        }

        $this->readings = $normalized;
        return $this;
    }

    private function hydrateFromRequest(Request $request): void
    {
        $validated = Validator::make($request->all(), [
            'controller_id' => ['required', 'string', 'uuid'],
            'readings' => ['nullable', 'array'],
            'readings.*.pin' => ['required_with:readings', 'string'],
            'readings.*.value' => ['required_with:readings'],
        ])->validate();

        $this
            ->setControllerId((string) $validated['controller_id'])
            ->setReadings((array) ($validated['readings'] ?? []));
    }

    private function normalizePin(string $pin): string
    {
        return strtoupper(trim($pin));
    }

    private function toNumber(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

