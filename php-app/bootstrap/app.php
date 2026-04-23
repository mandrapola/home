<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
        $middleware->alias([
            'proxy.hmac' => \App\Http\Middleware\VerifyProxyHmac::class,
            'alice.access' => \App\Http\Middleware\EnsureAliceAccessEnabled::class,
            'alice.resolve' => \App\Http\Middleware\ResolveAliceUser::class,
            'admin.user' => \App\Http\Middleware\EnsureAdminUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
