<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\ControllerReadingsReceived;
use App\Events\ControllerReportReceived;
use App\Http\Controllers\Controller;
use App\Models\ControllerPairing;
use App\Models\IoTController;
use App\Services\Report\ControllerMonitorPayloadService;
use App\Services\Report\ScenarioDesiredValueService;
use App\Support\ControllerReportResponseBuilder;
use App\Support\ReportReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControllerReportController extends Controller
{
    public function __construct(
        private readonly ScenarioDesiredValueService $scenarioDesiredValueService,
        private readonly ControllerMonitorPayloadService $controllerMonitorPayloadService,
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
        $targetRows = $this->scenarioDesiredValueService->findTargetRows($controllerId);
        $digitalOutputs = [];

        foreach ($targetRows as $row) {
            $desired = (((int) $row->desired_digital_value) > 0) ? 1 : 0;
            $pinKey = strtolower($this->normalizePin((string) $row->pin));
            $digitalOutputs[$pinKey] = $desired;
        }

        return $digitalOutputs;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $reportReading = new ReportReading($request);
        $controllerId = $reportReading->getControllerId();
        $readings = $reportReading->getReadings();

        $ip = (string) ($request->ip() ?? 'unknown');

        if (count($readings) === 0) {
            return $this->emptyReadingsResponse();
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
                        IoTController::MIN_INTERVAL_SECONDS,
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
            ->build();

        return response()->json($responsePayload);
    }
}
