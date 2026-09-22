<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $defaultLocale = (string) config('app.locale');
        $supportedLocales = array_values(array_unique([$defaultLocale, ...config('app.supported_locales', [])]));

        App::setLocale($request->getPreferredLanguage($supportedLocales) ?? $defaultLocale);

        $response = $next($request);
        $response->headers->set('Content-Language', App::getLocale());

        return $response;
    }
}
