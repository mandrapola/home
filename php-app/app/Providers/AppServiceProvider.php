<?php

namespace App\Providers;

use App\Events\ControllerPaired;
use App\Events\ControllerReadingsReceived;
use App\Events\ControllerReportReceived;
use App\Listeners\CleanupControllerPairingsOnPaired;
use App\Listeners\EnsureControllerExistsOnReport;
use App\Listeners\ProcessControllerReadingsOnReport;
use Illuminate\Support\Facades\Event;
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
        Event::listen(ControllerPaired::class, CleanupControllerPairingsOnPaired::class);
        Event::listen(ControllerReportReceived::class, EnsureControllerExistsOnReport::class);
        Event::listen(ControllerReadingsReceived::class, ProcessControllerReadingsOnReport::class);
    }
}
