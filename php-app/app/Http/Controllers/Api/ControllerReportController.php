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
use Illuminate\Support\Str;

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

    private function pendingApiToken(IoTController $controller): ?string
    {
        $pendingToken = trim((string) $controller->pending_api_token);
        if ($pendingToken !== '') {
            return $pendingToken;
        }

        if (trim((string) $controller->api_token_hash) !== '') {
            return null;
        }

        $apiToken = Str::random(64);
        $controller->api_token_hash = hash('sha256', $apiToken);
        $controller->pending_api_token = $apiToken;
        $controller->api_token_generated_at = now();
        $controller->save();

        return $apiToken;
    }

    private function acknowledgeBearerToken(string $controllerId): void
    {
        IoTController::query()
            ->where('id', $controllerId)
            ->whereNotNull('pending_api_token')
            ->update(['pending_api_token' => null]);
    }

    private function registrationResponse(ControllerRegistrationAttempt $attempt): JsonResponse
    {
        $payload = ControllerReportResponseBuilder::make()
            ->withSendIntervalSeconds(IoTController::MIN_INTERVAL_SECONDS)
            ->withDigitalOutputs([])
            ->withMonitor($attempt->status === 'challenge_pending' ? (string) $attempt->challenge_code : (string) $attempt->code)
            ->withControllerId('')
            ->withRegistrationRequired(true)
            ->build();

        return response()->json($payload);
    }

    public function provision(Request $request): JsonResponse
    {
        $reportReading = new ReportReading($request);
        $deviceUid = $reportReading->getDeviceUid();
        $readings = $reportReading->getReadings();
        $provisioningToken = trim((string) $request->bearerToken());

        if ($deviceUid === '' || count($readings) === 0) {
            return $this->emptyReadingsResponse();
        }

        if (strlen($provisioningToken) < 32) {
            return response()->json([
                'error' => 'provision_auth_failed',
                'message' => 'Provisioning bearer token is required.',
            ], 401);
        }

        $tokenHash = hash('sha256', $provisioningToken);
        $attempt = DB::transaction(function () use ($deviceUid, $tokenHash): ControllerRegistrationAttempt {
            ControllerRegistrationAttempt::query()
                ->whereIn('status', ['pending', 'challenge_pending'])
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $attempt = ControllerRegistrationAttempt::query()
                ->where('device_uid', $deviceUid)
                ->whereIn('status', ['pending', 'challenge_pending', 'claimed'])
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                return ControllerRegistrationAttempt::query()->create([
                    'id' => (string) Str::uuid(),
                    'device_uid' => $deviceUid,
                    'provisioning_token_hash' => $tokenHash,
                    'code' => $this->generateUniqueRegistrationCode(),
                    'status' => 'pending',
                    'last_seen_at' => now(),
                    'expires_at' => now()->addMinutes(self::REGISTRATION_TTL_MINUTES),
                ]);
            }

            return $attempt;
        });

        if (! hash_equals((string) $attempt->provisioning_token_hash, $tokenHash)) {
            return response()->json([
                'error' => 'provision_auth_failed',
                'message' => 'Invalid provisioning bearer token.',
            ], 401);
        }

        $attempt->last_seen_at = now();
        $attempt->save();

        if ($attempt->status !== 'claimed') {
            return $this->registrationResponse($attempt);
        }

        $controllerId = trim((string) $attempt->registered_controller_id);
        $controller = IoTController::query()->find($controllerId, ['id', 'api_token_hash', 'pending_api_token']);
        if (! $controller) {
            $attempt->status = 'expired';
            $attempt->save();

            return response()->json([
                'error' => 'controller_not_found',
                'message' => 'Registered controller no longer exists. Start registration again.',
            ], 404);
        }

        $apiToken = $this->pendingApiToken($controller);
        if ($apiToken === null) {
            return response()->json([
                'error' => 'token_already_delivered',
                'message' => 'Controller token was already activated.',
            ], 409);
        }

        return response()->json(ControllerReportResponseBuilder::make()
            ->withSendIntervalSeconds(IoTController::MIN_INTERVAL_SECONDS)
            ->withDigitalOutputs([])
            ->withMonitor(null)
            ->withControllerId($controllerId)
            ->withApiToken($apiToken)
            ->build());
    }

    public function __invoke(Request $request): JsonResponse
    {
        $reportReading = new ReportReading($request);
        $controllerId = $reportReading->getControllerId();
        $readings = $reportReading->getReadings();
        $ip = (string) ($request->ip() ?? 'unknown');

        if ($controllerId === '' || count($readings) === 0) {
            return $this->emptyReadingsResponse();
        }

        if (! $this->controllerExists($controllerId)) {
                return response()->json([
                    'error' => 'controller_not_found',
                    'message' => 'Controller not found.',
                ], 404);
        }

        $authenticatedControllerId = trim((string) $request->attributes->get('authenticated_controller_id', ''));
        if ($authenticatedControllerId !== '' && $authenticatedControllerId !== $controllerId) {
            return response()->json([
                'error' => 'controller_auth_failed',
                'message' => 'Bearer token does not belong to this controller.',
            ], 401);
        }

        $this->acknowledgeBearerToken($controllerId);

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
