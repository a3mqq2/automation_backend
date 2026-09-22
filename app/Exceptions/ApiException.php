<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode->value, 0, $previous);
    }

    public function render(): JsonResponse
    {
        return ApiErrorResponse::make($this->errorCode, $this->errors);
    }

    public function report(): void
    {
    }
}
