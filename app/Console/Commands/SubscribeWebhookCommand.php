<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Exceptions\MetaGraphException;
use App\Services\Meta\AppWebhookSubscriber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webhook:subscribe {--url= : Override the callback URL instead of using APP_URL}')]
#[Description('Register this application webhook callback URL with Meta for page events')]
class SubscribeWebhookCommand extends Command
{
    public function handle(AppWebhookSubscriber $subscriber): int
    {
        try {
            $callbackUrl = $this->option('url') ?: $subscriber->defaultCallbackUrl();

            $this->components->info("Registering {$callbackUrl}");
            $this->components->twoColumnDetail('Fields', implode(', ', $subscriber->subscribedFields()));

            $subscriber->subscribe($callbackUrl);

            $this->components->info('Webhook registered. Meta verified the callback URL.');
            $this->showSubscriptions($subscriber);

            return self::SUCCESS;
        } catch (MetaGraphException $exception) {
            $this->components->error('Meta rejected the request: '.$exception->graphMessage());
            $this->line('Make sure the URL is publicly reachable over HTTPS and that META_WEBHOOK_VERIFY_TOKEN matches.');

            return self::FAILURE;
        } catch (ApiException $exception) {
            $this->components->error('Missing configuration: '.$exception->errorCode->value);

            return self::FAILURE;
        }
    }

    private function showSubscriptions(AppWebhookSubscriber $subscriber): void
    {
        foreach ($subscriber->subscriptions() as $subscription) {
            $this->components->twoColumnDetail(
                (string) ($subscription['object'] ?? ''),
                (string) ($subscription['callback_url'] ?? ''),
            );
        }
    }
}
