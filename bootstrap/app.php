<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\SetLocaleFromHeader;
use App\Http\Middleware\VerifyMetaSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);
        $middleware->append(SetLocaleFromHeader::class);
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'client' => EnsureClient::class,
            'subscribed' => EnsureSubscriptionActive::class,
            'meta.signature' => VerifyMetaSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => app(ApiExceptionRenderer::class)->shouldRenderJson($request),
        );
        $exceptions->render(
            fn (Throwable $exception, Request $request) => app(ApiExceptionRenderer::class)->render($exception, $request),
        );
    })->create();
