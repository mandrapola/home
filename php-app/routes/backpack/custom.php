<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\IoTControllerCrudController;
use App\Http\Controllers\Admin\PaymentTransactionCrudController;
use App\Http\Controllers\Admin\PinCrudController;
use App\Http\Controllers\Admin\PlanCrudController;
use App\Http\Controllers\Admin\UserCrudController;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('users', UserCrudController::class);
    Route::get('users/{id}/plan', [UserCrudController::class, 'editPlan'])->name('backpack.users.plan.edit');
    Route::patch('users/{id}/plan', [UserCrudController::class, 'updatePlan'])->name('backpack.users.plan.update');

    Route::crud('controllers', IoTControllerCrudController::class);
    Route::crud('pins', PinCrudController::class);
    Route::crud('plans', PlanCrudController::class);
    Route::crud('payments', PaymentTransactionCrudController::class);
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
