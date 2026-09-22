<?php

namespace App\Services\Meta;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;

class AppWebhookSubscriber
{
    private const SUBSCRIBED_OBJECT = 'page';

    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function defaultCallbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/webhook';
    }

    public function subscribedFields(): array
    {
        return (array) config('meta.page_subscribed_fields');
    }

    public function subscribe(?string $callbackUrl = null): array
    {
        return $this->graph->post($this->subscriptionsPath(), $this->appAccessToken(), [
            'object' => self::SUBSCRIBED_OBJECT,
            'callback_url' => $callbackUrl ?? $this->defaultCallbackUrl(),
            'verify_token' => $this->verifyToken(),
            'fields' => implode(',', $this->subscribedFields()),
            'include_values' => true,
        ]);
    }

    public function subscriptions(): array
    {
        return $this->graph->get($this->subscriptionsPath(), $this->appAccessToken())['data'] ?? [];
    }

    public function unsubscribe(): array
    {
        return $this->graph->delete($this->subscriptionsPath(), $this->appAccessToken(), [
            'object' => self::SUBSCRIBED_OBJECT,
        ]);
    }

    private function subscriptionsPath(): string
    {
        return $this->appId().'/subscriptions';
    }

    private function appId(): string
    {
        $appId = (string) config('services.facebook.client_id');

        if ($appId === '') {
            throw new ApiException(ErrorCode::FacebookRequestFailed);
        }

        return $appId;
    }

    private function appAccessToken(): string
    {
        $appSecret = (string) config('services.facebook.client_secret');

        if ($appSecret === '') {
            throw new ApiException(ErrorCode::FacebookRequestFailed);
        }

        return $this->appId().'|'.$appSecret;
    }

    private function verifyToken(): string
    {
        $verifyToken = (string) config('meta.webhook_verify_token');

        if ($verifyToken === '') {
            throw new ApiException(ErrorCode::WebhookVerificationFailed);
        }

        return $verifyToken;
    }
}
