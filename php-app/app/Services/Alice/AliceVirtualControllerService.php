<?php

declare(strict_types=1);

namespace App\Services\Alice;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class AliceVirtualControllerService
{
    public const CONTROLLER_STATUS = 'virtual';
    public const DIGITAL_STYLE = 'external_control';
    public const SOURCE = 'alice';

    public function ensureForUser(User $user): void
    {
        $powerPins = $this->aliceEnabledPowerPins($user);
        if ($powerPins->isEmpty()) {
            return;
        }

        $controllerId = $this->ensureController($user);

        foreach ($powerPins as $powerPin) {
            $this->ensureVirtualPin($controllerId, $powerPin);
        }
    }

    public function updateCommandState(User $user, string $targetPinId, bool $on): bool
    {
        $this->ensureForUser($user);

        $updated = DB::table('pin')
            ->where('external_source', self::SOURCE)
            ->where('external_target_pin_id', $targetPinId)
            ->where('digital_style', self::DIGITAL_STYLE)
            ->update([
                'value' => $on ? 1 : 0,
                'value_updated_at' => now(),
            ]);

        return $updated > 0;
    }

    private function ensureController(User $user): string
    {
        $existingId = DB::table('controller')
            ->where('user_id', (int) $user->id)
            ->where('is_service', 1)
            ->where('discription', $this->controllerDescription($user))
            ->value('id');

        if (is_string($existingId) && $existingId !== '') {
            return $existingId;
        }

        $controllerId = (string) Uuid::uuid7();
        DB::table('controller')->insert([
            'id' => $controllerId,
            'user_id' => (int) $user->id,
            'name' => 'Алиса',
            'discription' => $this->controllerDescription($user),
            'send_interval_seconds' => 60,
            'status' => self::CONTROLLER_STATUS,
            'is_service' => 1,
            'last_seen_at' => now(),
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $controllerId;
    }

    private function ensureVirtualPin(string $controllerId, object $powerPin): void
    {
        $existing = DB::table('pin')
            ->where('external_source', self::SOURCE)
            ->where('external_target_pin_id', (string) $powerPin->id)
            ->first(['id']);

        $label = 'Алиса: ' . (string) ($powerPin->label ?: $powerPin->pin);
        $pinCode = $this->buildPinCode($controllerId, $powerPin);

        if ($existing) {
            DB::table('pin')
                ->where('id', (string) $existing->id)
                ->update([
                    'controller_id' => $controllerId,
                    'pin' => $pinCode,
                    'label' => $label,
                    'unit' => null,
                    'digital_style' => self::DIGITAL_STYLE,
                    'external_enabled' => 1,
                ]);

            return;
        }

        DB::table('pin')->insert([
            'id' => (string) Uuid::uuid7(),
            'controller_id' => $controllerId,
            'pin' => $pinCode,
            'label' => $label,
            'unit' => null,
            'digital_style' => self::DIGITAL_STYLE,
            'value' => 0,
            'value_updated_at' => now(),
            'desired_digital_value' => null,
            'desired_digital_updated_at' => null,
            'last_on_command_sent_at' => null,
            'show_on_chart' => 0,
            'show_on_report' => 0,
            'is_monitored' => 0,
            'external_enabled' => 1,
            'external_source' => self::SOURCE,
            'external_target_pin_id' => (string) $powerPin->id,
            'chart_range_hours' => 1,
            'enable_scenario' => 1,
        ]);
    }

    private function buildPinCode(string $controllerId, object $powerPin): string
    {
        $base = 'ALICE_' . Str::upper(preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $powerPin->pin));
        $base = trim($base, '_');
        if ($base === 'ALICE') {
            $base = 'ALICE_RELAY';
        }

        $duplicateExists = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->where('pin', $base)
            ->where('external_target_pin_id', '!=', (string) $powerPin->id)
            ->exists();

        if (! $duplicateExists && mb_strlen($base) <= 64) {
            return $base;
        }

        $suffix = '_' . Str::upper(substr(str_replace('-', '', (string) $powerPin->id), -6));
        return mb_substr($base, 0, 64 - mb_strlen($suffix)) . $suffix;
    }

    private function controllerDescription(User $user): string
    {
        return 'alice_virtual_controller:' . (int) $user->id;
    }

    /**
     * @return Collection<int, object>
     */
    private function aliceEnabledPowerPins(User $user): Collection
    {
        return DB::table('pin as p')
            ->join('controller as c', 'c.id', '=', 'p.controller_id')
            ->where('c.user_id', (int) $user->id)
            ->where('c.is_service', 0)
            ->where('p.digital_style', 'power')
            ->where('p.external_enabled', 1)
            ->select(['p.id', 'p.pin', 'p.label'])
            ->orderBy('c.name')
            ->orderBy('p.pin')
            ->get();
    }
}
