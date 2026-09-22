<?php

namespace App\Services\Automation\Engine;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use App\Exceptions\MetaGraphException;
use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Services\Activity\ActivityLogger;
use App\Services\Automation\Engine\Sinks\MessengerMessageSink;
use App\Services\Meta\MessengerProfileApi;
use App\Services\Meta\MessengerSendApi;

class FlowRunner
{
    private const PAYLOAD_PATTERN = '/^FLOW:(\d+):([A-Za-z0-9_-]{1,40}):([A-Za-z0-9_:-]{1,60})$/';

    public function __construct(
        private readonly FlowExecutor $executor,
        private readonly FlowEntryResolver $resolver,
        private readonly MessengerSendApi $messenger,
        private readonly MessengerProfileApi $profiles,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function handle(FacebookPage $page, Conversation $conversation, IncomingMessage $message): bool
    {
        if ($conversation->isAutomationPaused()) {
            return true;
        }

        $session = new ConversationFlowSession($conversation);
        $payloadTarget = $this->payloadTarget($message->payload);

        if ($payloadTarget !== null) {
            $flow = $this->runnableFlow($page, $payloadTarget['flow_id']);

            return $flow !== null
                && $this->start($page, $session, $flow, $this->versionFor($flow, $conversation), $message, $payloadTarget);
        }

        if ($conversation->isInFlow()) {
            $flow = $this->runnableFlow($page, (int) $conversation->bot_flow_id);
            $version = $flow === null ? null : $this->versionFor($flow, $conversation);

            if ($flow !== null && $version !== null && $this->start($page, $session, $flow, $version, $message, null)) {
                return true;
            }

            $session->clearFlowState();
        }

        foreach ($this->runnableFlows($page) as $flow) {
            if ($flow->publishedVersion !== null
                && $this->resolver->matchesTrigger($flow->publishedVersion->definition(), $message->text)) {
                return $this->start($page, $session, $flow, $flow->publishedVersion, $message, null);
            }
        }

        return false;
    }

    public function resumeAt(FacebookPage $page, Conversation $conversation, BotFlow $flow, BotFlowVersion $version, string $nodeId): void
    {
        $definition = $version->definition();
        $node = $definition->node($nodeId);

        if ($node === null) {
            return;
        }

        $context = $this->context($page, new ConversationFlowSession($conversation), $flow, $version, $definition, (string) $conversation->psid, '');

        $this->execute($page, $context, fn () => $this->executor->run($context, $node));
    }

    private function start(
        FacebookPage $page,
        ConversationFlowSession $session,
        BotFlow $flow,
        ?BotFlowVersion $version,
        IncomingMessage $message,
        ?array $payloadTarget,
    ): bool {
        if ($version === null) {
            return false;
        }

        $definition = $version->definition();
        $resolution = $this->resolver->resolve($definition, $session, $message->text, $payloadTarget);

        if (! $resolution->handled) {
            return false;
        }

        $this->ensureProfileVariables($page, $session, $message->psid);
        $context = $this->context($page, $session, $flow, $version, $definition, $message->psid, $message->text);

        $this->execute($page, $context, function () use ($context, $resolution, $session): void {
            if ($resolution->retryPrompt !== null) {
                $context->sink->sendText($resolution->retryPrompt);
                $context->recordNode((string) $session->currentNodeId(), 'ask_retry', ['text' => $resolution->retryPrompt]);

                return;
            }

            $this->executor->run($context, $resolution->node);
        });

        return true;
    }

    private function ensureProfileVariables(FacebookPage $page, ConversationFlowSession $session, string $psid): void
    {
        if (array_key_exists('first_name', $session->variables())) {
            return;
        }

        foreach ($this->profiles->variablesFor($page, $psid) as $name => $value) {
            $session->setVariable($name, $value);
        }
    }

    private function execute(FacebookPage $page, FlowRunContext $context, callable $action): void
    {
        try {
            $action();
        } catch (MetaGraphException $exception) {
            $exception->report();

            $this->activityLogger->record($page, ActivityEventType::FlowStep, ActivityStatus::Failed, array_merge(
                $this->payload($context),
                ['error' => ['code' => $exception->graphErrorCode(), 'message' => $exception->graphMessage()]],
            ));

            return;
        }

        if ($context->executedNodes !== []) {
            $this->activityLogger->record($page, ActivityEventType::FlowStep, ActivityStatus::Success, $this->payload($context));
        }
    }

    private function context(
        FacebookPage $page,
        ConversationFlowSession $session,
        BotFlow $flow,
        BotFlowVersion $version,
        $definition,
        string $psid,
        string $incomingText,
    ): FlowRunContext {
        return new FlowRunContext(
            flow: $flow,
            version: $version,
            definition: $definition,
            sink: new MessengerMessageSink($this->messenger, $page, $psid),
            session: $session,
            psid: $psid,
            incomingText: $incomingText,
            page: $page,
        );
    }

    private function payload(FlowRunContext $context): array
    {
        return [
            'flow_id' => $context->flow->id,
            'flow_name' => $context->flow->name,
            'flow_version' => $context->version->version,
            'psid' => $context->psid,
            'incoming_text' => $context->incomingText,
            'nodes' => $context->executedNodes,
        ];
    }

    private function payloadTarget(?string $payload): ?array
    {
        if ($payload === null || preg_match(self::PAYLOAD_PATTERN, $payload, $matches) !== 1) {
            return null;
        }

        return ['flow_id' => (int) $matches[1], 'node_id' => $matches[2], 'handle' => $matches[3]];
    }

    private function runnableFlow(FacebookPage $page, int $flowId): ?BotFlow
    {
        return $page->botFlows()->runnable()->with('publishedVersion')->find($flowId);
    }

    private function runnableFlows(FacebookPage $page): iterable
    {
        return $page->botFlows()->runnable()->with('publishedVersion')->orderBy('id')->get();
    }

    private function versionFor(BotFlow $flow, Conversation $conversation): ?BotFlowVersion
    {
        if ($conversation->bot_flow_id === $flow->id && $conversation->bot_flow_version_id !== null) {
            $pinned = $flow->versions()->find($conversation->bot_flow_version_id);

            if ($pinned !== null) {
                return $pinned;
            }
        }

        return $flow->publishedVersion;
    }
}
