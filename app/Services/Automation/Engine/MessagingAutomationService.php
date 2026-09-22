<?php

namespace App\Services\Automation\Engine;

use App\Enums\ActivityEventType;
use App\Enums\TriggerType;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Services\Meta\MessengerSendApi;

class MessagingAutomationService
{
    public function __construct(
        private readonly EventDeduplicator $deduplicator,
        private readonly FlowRunner $flows,
        private readonly RuleMatcher $rules,
        private readonly MessengerSendApi $messenger,
        private readonly AutomationActionExecutor $executor,
    ) {
    }

    public function handle(FacebookPage $page, array $event): void
    {
        $message = IncomingMessage::fromMessagingEvent($event);

        if ($message->psid === '' || $message->psid === $page->page_id || data_get($event, 'message.is_echo') === true) {
            return;
        }

        if (! $this->deduplicator->isFirstOccurrence("message:{$page->page_id}:{$this->eventKey($event, $message)}")) {
            return;
        }

        $conversation = Conversation::query()->firstOrCreate([
            'facebook_page_id' => $page->id,
            'psid' => $message->psid,
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        if ($this->flows->handle($page, $conversation, $message)) {
            return;
        }

        $rule = $this->rules->firstMatch($page, TriggerType::Message, $message->text);

        if ($rule === null || blank($rule->response_text)) {
            return;
        }

        $this->executor->run($page, ActivityEventType::MessageReply, [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'psid' => $message->psid,
            'incoming_text' => $message->text,
            'response_text' => $rule->response_text,
        ], fn () => $this->messenger->sendText($page, $message->psid, $rule->response_text));
    }

    private function eventKey(array $event, IncomingMessage $message): string
    {
        $messageId = data_get($event, 'message.mid') ?? data_get($event, 'postback.mid');

        return is_string($messageId) && $messageId !== ''
            ? $messageId
            : $message->psid.':'.data_get($event, 'timestamp', '');
    }
}
