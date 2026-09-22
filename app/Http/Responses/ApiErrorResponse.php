<?php

namespace App\Http\Responses;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

final class ApiErrorResponse
{
    public static function make(ErrorCode $errorCode, array $errors = [], array $headers = []): JsonResponse
    {
        $body = [
            'code' => $errorCode->value,
            'message' => __($errorCode->translationKey()),
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $errorCode->status(), $headers);
    }
}
