<?php

namespace App\Services\Automation\Engine;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Jobs\ProcessMetaWebhook;
use Illuminate\Support\Facades\Log;

class WebhookGateway
{
    private const SUBSCRIBE_MODE = 'subscribe';

    public function challenge(array $query): string
    {
        $mode = $this->queryValue($query, 'mode');
        $token = $this->queryValue($query, 'verify_token');
        $challenge = $this->queryValue($query, 'challenge');
        $expectedToken = (string) config('meta.webhook_verify_token');

        if ($mode !== self::SUBSCRIBE_MODE || $expectedToken === '' || $challenge === '' || ! hash_equals($expectedToken, $token)) {
            throw new ApiException(ErrorCode::WebhookVerificationFailed);
        }

        return $challenge;
    }

    public function accept(array $payload): void
    {
        if (config('meta.log_webhook_payloads')) {
            Log::info('Meta webhook payload received', ['payload' => $payload]);
        }

        if (($payload['object'] ?? null) === 'page') {
            ProcessMetaWebhook::dispatch($payload);
        }
    }

    private function queryValue(array $query, string $name): string
    {
        $value = $query["hub_{$name}"] ?? $query["hub.{$name}"] ?? '';

        return is_string($value) ? $value : '';
    }
}
