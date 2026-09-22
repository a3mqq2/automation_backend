<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public function shouldRenderJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderJson($request)) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => ApiErrorResponse::make(ErrorCode::ValidationFailed, $exception->errors()),
            $exception instanceof AuthenticationException => ApiErrorResponse::make(ErrorCode::Unauthenticated),
            $exception instanceof ThrottleRequestsException => ApiErrorResponse::make(ErrorCode::TooManyAttempts, headers: $exception->getHeaders()),
            $exception instanceof AccessDeniedHttpException => ApiErrorResponse::make(ErrorCode::Forbidden),
            $exception instanceof NotFoundHttpException => ApiErrorResponse::make(ErrorCode::ResourceNotFound),
            $exception instanceof MethodNotAllowedHttpException => ApiErrorResponse::make(ErrorCode::MethodNotAllowed, headers: $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface, (bool) config('app.debug') => null,
            default => ApiErrorResponse::make(ErrorCode::ServerError),
        };
    }
}
