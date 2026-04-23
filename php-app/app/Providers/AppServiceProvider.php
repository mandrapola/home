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
            $key = (string) ($request->header('X-Alice-User-Id') ?: $request->ip());
            return Limit::perMinute(120)->by('alice-api:' . $key);
        });

        Event::listen(ControllerPaired::class, CleanupControllerPairingsOnPaired::class);
        Event::listen(ControllerReportReceived::class, EnsureControllerExistsOnReport::class);
        Event::listen(ControllerReadingsReceived::class, ProcessControllerReadingsOnReport::class);
    }
}
