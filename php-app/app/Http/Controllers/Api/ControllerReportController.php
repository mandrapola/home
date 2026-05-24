<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\ControllerReadingsReceived;
use App\Events\ControllerReportReceived;
use App\Http\Controllers\Controller;
use App\Models\ControllerPairing;
use App\Models\ControllerRegistrationAttempt;
use App\Models\IoTController;
use App\Models\User;
use App\Services\Billing\PlanLimitService;
use App\Services\Billing\ReportRateLimitService;
use App\Services\Report\ControllerMonitorPayloadService;
use App\Services\Report\ScenarioDesiredValueService;
use App\Support\ControllerReportResponseBuilder;
use App\Support\ReportReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControllerReportController extends Controller
{
    private const REGISTRATION_TTL_MINUTES = 10;

    public function __construct(
        private readonly ScenarioDesiredValueService $scenarioDesiredValueService,
        private readonly ControllerMonitorPayloadService $controllerMonitorPayloadService,
        private readonly ReportRateLimitService $reportRateLimitService,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    private function emptyReadingsResponse(): JsonResponse
    {
        return response()->json([
            'error' => 'empty_readings',
            'message' => 'No valid sensor readings provided',
        ], 400);
    }

    private function normalizePin(string $pin): string
    {
        return strtoupper(trim($pin));
    }

    private function buildDigitalOutputs(string $controllerId): array
    {
        if (! $this->planLimitService->scenarioExecutionAllowedForController($controllerId)) {
            return [];
        }

        $targetRows = $this->scenarioDesiredValueService->findTargetRows($controllerId);
        $digitalOutputs = [];

        foreach ($targetRows as $row) {
            $desired = (((int) $row->desired_digital_value) > 0) ? 1 : 0;
            $pinKey = strtolower($this->normalizePin((string) $row->pin));
            $digitalOutputs[$pinKey] = $desired;
        }

        return $digitalOutputs;
    }

    private function generateUniqueRegistrationCode(): string
    {
        $takenCodes = ControllerRegistrationAttempt::query()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->pluck('code')
            ->mapWithKeys(fn ($code) => [(string) $code => true])
            ->all();

        ControllerPairing::query()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->pluck('code')
            ->each(function ($code) use (&$takenCodes): void {
                $takenCodes[(string) $code] = true;
            });

        for ($attempt = 0; $attempt < 10000; $attempt++) {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! isset($takenCodes[$code])) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate unique registration code');
    }

    private function claimedControllerIdForDevice(string $deviceUid): ?string
    {
        $controllerId = ControllerRegistrationAttempt::query()
            ->where('device_uid', $deviceUid)
            ->where('status', 'claimed')
            ->whereNotNull('registered_controller_id')
            ->orderByDesc('claimed_at')
            ->value('registered_controller_id');

        return $controllerId !== null ? (string) $controllerId : null;
    }

    private function controllerExists(string $controllerId): bool
    {
        return DB::table('controller')
            ->where('id', $controllerId)
            ->exists();
    }

    private function minimumSendIntervalSecondsForController(string $controllerId): int
    {
        $ownerId = (int) (DB::table('controller')->where('id', $controllerId)->value('user_id') ?? 0);

        $minimumSeconds = IoTController::MIN_INTERVAL_SECONDS;
        if ($ownerId <= 0) {
            return $minimumSeconds;
        }

        $user = User::query()->find($ownerId);
        if ($user) {
            $effectivePlan = $this->planLimitService->resolveEffectivePlanForUser($user);
            $minimumSeconds = max($minimumSeconds, (int) ($effectivePlan?->min_report_interval_seconds ?? 0));
        }

        return min(IoTController::MAX_INTERVAL_SECONDS, $minimumSeconds);
    }

    private function registrationCodeResponse(string $deviceUid): JsonResponse
    {
        $attempt = DB::transaction(function () use ($deviceUid): ControllerRegistrationAttempt {
            ControllerRegistrationAttempt::query()
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $activeAttempt = ControllerRegistrationAttempt::query()
                ->where('device_uid', $deviceUid)
                ->whereIn('status', ['pending', 'challenge_pending'])
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($activeAttempt) {
                $activeAttempt->last_seen_at = now();
                $activeAttempt->save();

                return $activeAttempt;
            }

            return ControllerRegistrationAttempt::query()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'device_uid' => $deviceUid,
                'code' => $this->generateUniqueRegistrationCode(),
                'status' => 'pending',
                'last_seen_at' => now(),
                'expires_at' => now()->addMinutes(self::REGISTRATION_TTL_MINUTES),
            ]);
        });

        $payload = ControllerReportResponseBuilder::make()
            ->withSendIntervalSeconds(IoTController::MIN_INTERVAL_SECONDS)
            ->withDigitalOutputs([])
            ->withMonitor($attempt->status === 'challenge_pending' ? (string) $attempt->challenge_code : (string) $attempt->code)
            ->withControllerId('')
            ->withRegistrationRequired(true)
            ->build();

        return response()->json($payload);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $reportReading = new ReportReading($request);
        $controllerId = $reportReading->getControllerId();
        $deviceUid = $reportReading->getDeviceUid();
        $readings = $reportReading->getReadings();

        $ip = (string) ($request->ip() ?? 'unknown');

        if (count($readings) === 0) {
            return $this->emptyReadingsResponse();
        }

        if ($controllerId === '' && $deviceUid !== '') {
            $claimedControllerId = $this->claimedControllerIdForDevice($deviceUid);
            if ($claimedControllerId === null || ! $this->controllerExists($claimedControllerId)) {
                return $this->registrationCodeResponse($deviceUid);
            }

            $controllerId = $claimedControllerId;
        }

        if ($controllerId !== '' && ! $this->controllerExists($controllerId)) {
            if ($deviceUid !== '') {
                $claimedControllerId = $this->claimedControllerIdForDevice($deviceUid);
                if ($claimedControllerId !== null && $this->controllerExists($claimedControllerId)) {
                    $controllerId = $claimedControllerId;
                } else {
                    return $this->registrationCodeResponse($deviceUid);
                }
            } else {
                return response()->json([
                    'error' => 'controller_not_found',
                    'message' => 'Controller not found and device_uid is missing.',
                ], 404);
            }
        }

        $rateLimit = $this->reportRateLimitService->checkAndRecordAccepted($controllerId);
        if (! $rateLimit['allowed']) {
            return response()->json([
                'error' => 'rate_limit',
                'message' => __('For your plan, data can be sent no more than once every :minutes minutes.', [
                    'minutes' => max(1, (int) ceil(((int) $rateLimit['interval_seconds']) / 60)),
                ]),
                'retry_after_seconds' => (int) $rateLimit['retry_after_seconds'],
            ], 429);
        }

        event(new ControllerReportReceived($controllerId, $ip));

        $result = DB::transaction(function () use ($controllerId, $readings): array {
            IoTController::query()
                ->where('id', $controllerId)
                ->update(['last_seen_at' => now()]);

            $controller = DB::table('controller')
                ->where('id', $controllerId)
                ->first(['id', 'send_interval_seconds']);

            if (! $controller) {
                throw new \RuntimeException('Controller was not created by listener.');
            }

            event(new ControllerReadingsReceived($controllerId, $readings));

            return [
                'send_interval_seconds' => min(
                    IoTController::MAX_INTERVAL_SECONDS,
                    max(
                        $this->minimumSendIntervalSecondsForController($controllerId),
                        (int) (($controller->send_interval_seconds ?? IoTController::MIN_INTERVAL_SECONDS))
                    )
                ),
                'digital_outputs' => $this->buildDigitalOutputs($controllerId),
            ];
        });

        $activePairing = ControllerPairing::query()
            ->where('controller_id', $controllerId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if ($activePairing && $activePairing->displayed_at === null) {
            $activePairing->displayed_at = now();
            $activePairing->save();
        }

        $monitorValue = $activePairing
            ? (string) $activePairing->code
            : $this->controllerMonitorPayloadService->buildMonitorValue($controllerId);

        $responsePayload = ControllerReportResponseBuilder::make()
            ->withSendIntervalSeconds((int) ($result['send_interval_seconds'] ?? 5))
            ->withDigitalOutputs((array) ($result['digital_outputs'] ?? []))
            ->withMonitor($monitorValue)
            ->withControllerId($controllerId)
            ->build();

        return response()->json($responsePayload);
    }
}
