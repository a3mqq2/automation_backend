<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Exceptions\MetaGraphException;
use App\Services\Meta\AppWebhookSubscriber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webhook:status')]
#[Description('Show the webhook subscriptions Meta currently has for this application')]
class ShowWebhookSubscriptionsCommand extends Command
{
    public function handle(AppWebhookSubscriber $subscriber): int
    {
        try {
            $subscriptions = $subscriber->subscriptions();
        } catch (MetaGraphException $exception) {
            $this->components->error('Meta rejected the request: '.$exception->graphMessage());

            return self::FAILURE;
        } catch (ApiException $exception) {
            $this->components->error('Missing configuration: '.$exception->errorCode->value);

            return self::FAILURE;
        }

        if ($subscriptions === []) {
            $this->components->warn('No webhook is registered. Run: php artisan webhook:subscribe');

            return self::FAILURE;
        }

        foreach ($subscriptions as $subscription) {
            $this->components->twoColumnDetail('Object', (string) ($subscription['object'] ?? ''));
            $this->components->twoColumnDetail('Callback URL', (string) ($subscription['callback_url'] ?? ''));
            $this->components->twoColumnDetail('Active', ($subscription['active'] ?? false) ? 'yes' : 'no');
            $this->components->twoColumnDetail('Fields', implode(', ', array_map(
                fn (array $field) => (string) ($field['name'] ?? ''),
                (array) ($subscription['fields'] ?? []),
            )));
        }

        return self::SUCCESS;
    }
}
