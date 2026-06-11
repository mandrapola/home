<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PairingController;
use App\Http\Controllers\ScenesController;
use App\Http\Controllers\AliceLinkController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\OAuth\AliceOAuthProviderController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'plans' => Plan::query()
            ->where('is_active', true)
            ->orderBy('daily_price_units')
            ->limit(3)
            ->get(),
    ]);
});

Route::get('/home-arduino', function () {
    return view('home-arduino');
})->name('home-arduino');

Route::get('/home-arduino/controller-build', function () {
    return view('home-arduino-controller-build');
})->name('home-arduino.controller-build');

Route::get('/home-arduino/openwrt-proxy', function () {
    return view('home-arduino-openwrt-proxy');
})->name('home-arduino.openwrt-proxy');

Route::get('/home-arduino/site-faq', function () {
    return view('home-arduino-site-faq');
})->name('home-arduino.site-faq');

Route::get('/home-arduino/server-contract', function () {
    return view('home-arduino-server-contract');
})->name('home-arduino.server-contract');

Route::get('/brand-verification', function () {
    return view('brand-verification');
})->name('brand-verification');

Route::get('/oauth/authorize', [AliceOAuthProviderController::class, 'authorize'])
    ->name('oauth.alice.authorize');

Route::post('/oauth/authorize', [AliceOAuthProviderController::class, 'approve'])
    ->middleware('auth')
    ->name('oauth.alice.approve');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/adding-a-new-controller', function () {
    return view('adding-a-new-controller');
})->middleware(['auth', 'verified'])->name('adding-a-new-controller');

Route::get('/scenes', [ScenesController::class, 'index'])->middleware(['auth', 'verified'])->name('scenes');

Route::middleware('auth')->group(function () {
    Route::get('/plans', [PlanController::class, 'index'])->name('user.plans.index');
    Route::post('/plans/{plan}/select', [PlanController::class, 'select'])->name('user.plans.select');
    Route::post('/plans/{plan}/pay', [PlanController::class, 'pay'])->name('user.plans.pay');
    Route::get('/api/plan/limits', [PlanController::class, 'limits'])->name('user.plans.limits');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/admin/users/{targetUser}/alice-access', [ProfileController::class, 'updateUserAliceAccess'])
        ->middleware('admin.user')
        ->name('admin.users.alice-access.update');
    Route::get('/profile/alice/connect', [AliceLinkController::class, 'redirectToProvider'])->name('profile.alice.connect');
    Route::get('/profile/alice/callback', [AliceLinkController::class, 'handleProviderCallback'])->name('profile.alice.callback');
    Route::post('/profile/alice/disconnect', [AliceLinkController::class, 'disconnect'])->name('profile.alice.disconnect');
    Route::get('/api/scenes/data', [ScenesController::class, 'data']);
    Route::post('/api/scenes/scenario-definitions', [ScenesController::class, 'storeDefinition']);
    Route::put('/api/scenes/scenario-definitions/{definitionId}', [ScenesController::class, 'updateDefinition']);
    Route::delete('/api/scenes/scenario-definitions/{definitionId}', [ScenesController::class, 'deleteDefinition']);
    Route::post('/api/scenes/conditions', [ScenesController::class, 'storeCondition']);
    Route::put('/api/scenes/conditions/{conditionId}', [ScenesController::class, 'updateCondition']);
    Route::delete('/api/scenes/conditions/{conditionId}', [ScenesController::class, 'deleteCondition']);
    Route::put('/api/scenes/targets/{pinId}/enabled', [ScenesController::class, 'setTargetScenarioEnabled']);

    Route::prefix('api/pairing')->group(function () {
        Route::get('/my-controllers', [PairingController::class, 'myControllers']);
        Route::get('/my-controllers/{controllerId}/pins', [PairingController::class, 'myControllerPins']);
        Route::get('/my-controllers/{controllerId}/power-events', [PairingController::class, 'myControllerPowerEvents']);
        Route::get('/my-controllers/{controllerId}/pins/chart-data', [PairingController::class, 'myControllerPinChartData']);
        Route::put('/my-controllers/{controllerId}/pins/{pinId}/chart-range-hours', [PairingController::class, 'updateMyControllerPinChartRangeHours']);
        Route::put('/my-controllers/{controllerId}/pins/{pinId}/settings', [PairingController::class, 'updateMyControllerPinSettings']);
        Route::put('/my-controllers/{controllerId}/pins/{pinId}/desired-digital-value', [PairingController::class, 'updateMyControllerPinDesiredDigitalValue']);
        Route::put('/my-controllers/{controllerId}/settings', [PairingController::class, 'updateMyControllerSettings']);
        Route::delete('/my-controllers/{controllerId}', [PairingController::class, 'deleteMyController']);
        Route::get('/unclaimed-controllers', [PairingController::class, 'unclaimed']);
        Route::post('/start-all', [PairingController::class, 'startAll']);
        Route::post('/confirm-by-code', [PairingController::class, 'confirmByCode']);
        Route::post('/{controllerId}/start', [PairingController::class, 'start']);
        Route::post('/{controllerId}/confirm', [PairingController::class, 'confirm']);
    });

});

require __DIR__.'/auth.php';
