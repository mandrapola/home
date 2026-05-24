<?php

declare(strict_types=1);

namespace App\Services\Alice;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AliceSmartHomeService
{
    private function alertsEnabled(): bool
    {
        return (bool) config('services.alice.enabled', false)
            && (bool) config('services.alice.alerts_enabled', false);
    }

    public function buildDevicesPayload(User $user, ?string $requestId = null): array
    {
        $devices = $this->ownedPins($user)->map(fn (object $pin) => $this->mapDevice($pin))->filter()->values()->all();

        return [
            'request_id' => $requestId,
            'payload' => [
                'user_id' => (string) $user->id,
                'devices' => $devices,
            ],
        ];
    }

    public function buildQueryPayload(User $user, array $deviceIds, ?string $requestId = null): array
    {
        $pins = $this->ownedPins($user)->keyBy(fn (object $row) => (string) $row->id);
        $devices = [];

        foreach ($deviceIds as $deviceId) {
            $key = (string) $deviceId;
            $pin = $pins->get($key);
            if (! $pin) {
                $devices[] = [
                    'id' => $key,
                    'error_code' => 'DEVICE_NOT_FOUND',
                ];
                continue;
            }

            $devices[] = $this->mapDeviceState($pin);
        }

        return [
            'request_id' => $requestId,
            'payload' => [
                'devices' => $devices,
            ],
        ];
    }

    public function buildActionPayload(User $user, array $devicesInput, ?string $requestId = null): array
    {
        $pins = $this->ownedPins($user)->keyBy(fn (object $row) => (string) $row->id);
        $devices = [];

        foreach ($devicesInput as $deviceInput) {
            $deviceId = (string) ($deviceInput['id'] ?? '');
            if ($deviceId === '') {
                $devices[] = [
                    'id' => '',
                    'action_result' => [
                        'status' => 'ERROR',
                        'error_code' => 'DEVICE_NOT_FOUND',
                    ],
                ];
                continue;
            }

            $pin = $pins->get($deviceId);
            if (! $pin) {
                $devices[] = [
                    'id' => $deviceId,
                    'capabilities' => [[
                        'type' => 'devices.capabilities.on_off',
                        'state' => [
                            'instance' => 'on',
                            'action_result' => [
                                'status' => 'ERROR',
                                'error_code' => 'DEVICE_NOT_FOUND',
                            ],
                        ],
                    ]],
                    'action_result' => [
                        'status' => 'ERROR',
                        'error_code' => 'DEVICE_NOT_FOUND',
                    ],
                ];
                continue;
            }

            if ((string) $pin->digital_style !== 'power') {
                $devices[] = [
                    'id' => $deviceId,
                    'capabilities' => [[
                        'type' => 'devices.capabilities.on_off',
                        'state' => [
                            'instance' => 'on',
                            'action_result' => [
                                'status' => 'ERROR',
                                'error_code' => 'INVALID_ACTION',
                            ],
                        ],
                    ]],
                    'action_result' => [
                        'status' => 'ERROR',
                        'error_code' => 'INVALID_ACTION',
                    ],
                ];
                continue;
            }

            $on = $this->extractPowerActionValue($deviceInput);
            if ($on === null) {
                $devices[] = [
                    'id' => $deviceId,
                    'capabilities' => [[
                        'type' => 'devices.capabilities.on_off',
                        'state' => [
                            'instance' => 'on',
                            'action_result' => [
                                'status' => 'ERROR',
                                'error_code' => 'INVALID_ACTION',
                            ],
                        ],
                    ]],
                    'action_result' => [
                        'status' => 'ERROR',
                        'error_code' => 'INVALID_ACTION',
                    ],
                ];
                continue;
            }

            DB::table('pin')
                ->where('id', $deviceId)
                ->update([
                    'desired_digital_value' => $on ? 1 : 0,
                    'desired_digital_updated_at' => now(),
                    'enable_scenario' => 0,
                ]);

            $devices[] = [
                'id' => $deviceId,
                'capabilities' => [[
                    'type' => 'devices.capabilities.on_off',
                    'state' => [
                        'instance' => 'on',
                        'value' => $on,
                        'action_result' => [
                            'status' => 'DONE',
                        ],
                    ],
                ]],
                'action_result' => [
                    'status' => 'DONE',
                ],
            ];
        }

        return [
            'request_id' => $requestId,
            'payload' => [
                'devices' => $devices,
            ],
        ];
    }

    public function unlink(User $user, string $yandexUserId): void
    {
        DB::table('alice_accounts')
            ->where('user_id', $user->id)
            ->where('yandex_user_id', $yandexUserId)
            ->update([
                'unlinked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function ownedPins(User $user): Collection
    {
        $query = DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', $user->id)
            ->where(function ($q): void {
                $q->where('p.digital_style', 'power')
                    ->orWhere('p.digital_style', 'like', 'sensor%');
            })
            ->select([
                'p.id',
                'p.pin',
                'p.label',
                'p.unit',
                'p.digital_style',
                'p.value',
                'p.value_updated_at',
                'c.last_seen_at as controller_last_seen_at',
            ])
            ->distinct();

        if (Schema::hasColumn('pin', 'external_enabled')) {
            $query->where('p.external_enabled', 1);
        }

        return $query->orderBy('p.pin')->get();
    }

    private function mapDevice(object $pin): ?array
    {
        if ((string) $pin->digital_style === 'power') {
            return [
                'id' => (string) $pin->id,
                'name' => (string) ($pin->label ?: $pin->pin),
                'type' => 'devices.types.socket',
                'capabilities' => [[
                    'type' => 'devices.capabilities.on_off',
                    'retrievable' => true,
                    'reportable' => $this->alertsEnabled(),
                    'parameters' => [
                        'instance' => 'on',
                    ],
                ]],
            ];
        }

        $property = $this->mapSensorPropertyDefinition($pin);
        if (! $property) {
            return null;
        }

        return [
            'id' => (string) $pin->id,
            'name' => (string) ($pin->label ?: $pin->pin),
            'type' => 'devices.types.sensor',
            'properties' => [$property],
        ];
    }

    private function mapDeviceState(object $pin): array
    {
        if ((string) $pin->digital_style === 'power') {
            if (! $this->isControllerFresh($pin)) {
                return [
                    'id' => (string) $pin->id,
                    'error_code' => 'DEVICE_UNREACHABLE',
                ];
            }

            return [
                'id' => (string) $pin->id,
                'capabilities' => [[
                    'type' => 'devices.capabilities.on_off',
                    'state' => [
                        'instance' => 'on',
                        'value' => ((int) round((float) ($pin->value ?? 0))) > 0,
                    ],
                ]],
            ];
        }

        $definition = $this->mapSensorPropertyDefinition($pin);
        if (! $definition) {
            return [
                'id' => (string) $pin->id,
                'error_code' => 'DEVICE_UNREACHABLE',
            ];
        }

        $recent = DB::table('pin_data')
            ->where('pin_id', (string) $pin->id)
            ->orderByDesc('created_at')
            ->limit(2)
            ->get(['value', 'created_at']);

        if ($recent->count() === 0) {
            return [
                'id' => (string) $pin->id,
                'properties' => [[
                    'type' => 'devices.properties.float',
                    'state' => [
                        'instance' => $definition['parameters']['instance'],
                        'value' => null,
                        'error_code' => 'DEVICE_UNREACHABLE',
                    ],
                ]],
            ];
        }

        $latestAt = CarbonImmutable::parse((string) $recent->first()->created_at);
        $staleAfter = max(30, (int) config('services.alice.stale_sensor_seconds', 600));
        if ($latestAt->diffInSeconds(now()) > $staleAfter) {
            return [
                'id' => (string) $pin->id,
                'properties' => [[
                    'type' => 'devices.properties.float',
                    'state' => [
                        'instance' => $definition['parameters']['instance'],
                        'value' => null,
                        'error_code' => 'DEVICE_UNREACHABLE',
                    ],
                ]],
            ];
        }

        $avg = $recent->avg(fn (object $r): float => (float) $r->value);
        return [
            'id' => (string) $pin->id,
            'properties' => [[
                'type' => 'devices.properties.float',
                'state' => [
                    'instance' => $definition['parameters']['instance'],
                    'value' => (float) $avg,
                ],
            ]],
        ];
    }

    private function mapSensorPropertyDefinition(object $pin): ?array
    {
        $style = (string) $pin->digital_style;
        $unit = strtolower(trim((string) ($pin->unit ?? '')));

        if ($style === 'sensor_temperature' && in_array($unit, ['celsius', 'fahrenheit'], true)) {
            return [
                'type' => 'devices.properties.float',
                'retrievable' => true,
                'reportable' => false,
                'parameters' => [
                    'instance' => 'temperature',
                    'unit' => $unit === 'fahrenheit' ? 'unit.temperature.fahrenheit' : 'unit.temperature.celsius',
                ],
            ];
        }

        if ($style === 'sensor_humidity' && $unit === 'percent') {
            return [
                'type' => 'devices.properties.float',
                'retrievable' => true,
                'reportable' => false,
                'parameters' => [
                    'instance' => 'humidity',
                    'unit' => 'unit.percent',
                ],
            ];
        }

        if ($style === 'sensor_light' && $unit === 'lux') {
            return [
                'type' => 'devices.properties.float',
                'retrievable' => true,
                'reportable' => false,
                'parameters' => [
                    'instance' => 'illumination',
                    'unit' => 'unit.illumination.lux',
                ],
            ];
        }

        if ($style === 'sensor_pressure' && in_array($unit, ['kpa', 'bar'], true)) {
            return [
                'type' => 'devices.properties.float',
                'retrievable' => true,
                'reportable' => false,
                'parameters' => [
                    'instance' => 'pressure',
                    'unit' => $unit === 'bar' ? 'unit.pressure.bar' : 'unit.pressure.kilopascal',
                ],
            ];
        }

        return null;
    }

    private function extractPowerActionValue(array $deviceInput): ?bool
    {
        $capabilities = $deviceInput['capabilities'] ?? [];
        if (! is_array($capabilities)) {
            return null;
        }

        foreach ($capabilities as $capability) {
            if (! is_array($capability)) {
                continue;
            }

            if (($capability['type'] ?? '') !== 'devices.capabilities.on_off') {
                continue;
            }

            $state = $capability['state'] ?? [];
            if (! is_array($state) || ($state['instance'] ?? '') !== 'on') {
                continue;
            }

            if (! array_key_exists('value', $state)) {
                continue;
            }

            return (bool) $state['value'];
        }

        return null;
    }

    private function isControllerFresh(object $pin): bool
    {
        $staleAfter = max(30, (int) config('services.alice.stale_sensor_seconds', 600));
        $reference = null;

        $controllerSeenAt = trim((string) ($pin->controller_last_seen_at ?? ''));
        if ($controllerSeenAt !== '') {
            $reference = CarbonImmutable::parse($controllerSeenAt);
        } else {
            $valueUpdatedAt = trim((string) ($pin->value_updated_at ?? ''));
            if ($valueUpdatedAt !== '') {
                $reference = CarbonImmutable::parse($valueUpdatedAt);
            }
        }

        if (! $reference) {
            return false;
        }

        return $reference->diffInSeconds(now()) <= $staleAfter;
    }
}
