<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = is_object($user) ? (string) ($user->locale ?? '') : '';

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = (string) config('app.locale', 'ru');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
