<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->cookie('vidrieria_locale', 'es');

        app()->setLocale(in_array($locale, ['es', 'en'], true) ? $locale : 'es');

        return $next($request);
    }
}
