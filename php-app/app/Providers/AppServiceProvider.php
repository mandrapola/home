<?php

namespace App\Providers;

use App\Events\ControllerPaired;
use App\Events\ControllerReadingsReceived;
use App\Events\ControllerReportReceived;
use App\Listeners\CleanupControllerPairingsOnPaired;
use App\Listeners\EnsureControllerExistsOnReport;
use App\Listeners\ProcessControllerReadingsOnReport;
use Illuminate\Support\Facades\Event;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('alice-api', function (Request $request) {
            $auth = trim((string) $request->header('Authorization', ''));
            $headerUser = trim((string) $request->header('X-Alice-User-Id', ''));
            $keySource = $auth !== '' ? $auth : ($headerUser !== '' ? $headerUser : (string) $request->ip());
            $key = hash('sha256', $keySource);
            return Limit::perMinute(120)->by('alice-api:' . $key);
        });

        RateLimiter::for('alice-oauth-token', function (Request $request) {
            $clientId = trim((string) $request->input('client_id', ''));
            $keySource = $clientId !== '' ? $clientId . '|' . $request->ip() : (string) $request->ip();
            return Limit::perMinute(60)->by('alice-oauth-token:' . hash('sha256', $keySource));
        });

        RateLimiter::for('controller-provision', function (Request $request) {
            $deviceUid = trim((string) $request->input('device_uid', ''));
            $keySource = $deviceUid !== '' ? $deviceUid . '|' . $request->ip() : (string) $request->ip();
            return Limit::perMinute(30)->by('controller-provision:' . hash('sha256', $keySource));
        });

        Event::listen(ControllerPaired::class, CleanupControllerPairingsOnPaired::class);
        Event::listen(ControllerReportReceived::class, EnsureControllerExistsOnReport::class);
        Event::listen(ControllerReadingsReceived::class, ProcessControllerReadingsOnReport::class);
    }
}
