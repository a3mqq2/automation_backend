<?php

namespace App\Services\Meta;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;

class SignedRequestParser
{
    private const ALGORITHM = 'HMAC-SHA256';

    public function parse(?string $signedRequest): array
    {
        $appSecret = (string) config('services.facebook.client_secret');

        if ($appSecret === '' || $signedRequest === null || ! str_contains($signedRequest, '.')) {
            throw new ApiException(ErrorCode::WebhookInvalidSignature);
        }

        [$encodedSignature, $encodedPayload] = explode('.', $signedRequest, 2);
        $signature = $this->decode($encodedSignature);
        $payload = json_decode((string) $this->decode($encodedPayload), true);

        if ($signature === false || ! is_array($payload) || ($payload['algorithm'] ?? null) !== self::ALGORITHM) {
            throw new ApiException(ErrorCode::WebhookInvalidSignature);
        }

        if (! hash_equals(hash_hmac('sha256', $encodedPayload, $appSecret, true), $signature)) {
            throw new ApiException(ErrorCode::WebhookInvalidSignature);
        }

        return $payload;
    }

    public function facebookUserId(?string $signedRequest): string
    {
        $userId = (string) ($this->parse($signedRequest)['user_id'] ?? '');

        if ($userId === '') {
            throw new ApiException(ErrorCode::WebhookInvalidSignature);
        }

        return $userId;
    }

    private function decode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
