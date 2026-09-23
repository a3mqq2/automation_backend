<?php

namespace App\Services\Automation\Engine;

use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use App\Services\Automation\Engine\Sinks\TranscriptMessageSink;

class FlowSimulator
{
    public function __construct(
        private readonly FlowExecutor $executor,
        private readonly FlowEntryResolver $resolver,
    ) {
    }

    public function run(BotFlow $flow, array $messages): array
    {
        $definition = $flow->draftDefinition();
        $version = new BotFlowVersion(['bot_flow_id' => $flow->id, 'version' => 0, 'definition' => (array) $flow->flow_json]);
        $session = new SimulatedFlowSession();
        $page = $flow->facebookPage()->first();
        $transcript = [];

        foreach ($messages as $text) {
            $text = (string) $text;
            $sink = new TranscriptMessageSink();
            $context = new FlowRunContext(
                flow: $flow,
                version: $version,
                definition: $definition,
                sink: $sink,
                session: $session,
                psid: 'simulator',
                incomingText: $text,
                page: $page,
                simulating: true,
            );

            $resolution = $this->resolver->resolve($context->definition, $session, $text);
            $transcript[] = ['from' => 'customer', 'text' => $text];

            if (! $resolution->handled) {
                $transcript[] = ['from' => 'system', 'code' => 'no_match'];

                continue;
            }

            if ($resolution->retryPrompt !== null) {
                $sink->sendText($resolution->retryPrompt);
            } else {
                $this->executor->run($context, $resolution->node);
            }

            foreach ($sink->messages() as $message) {
                $transcript[] = array_merge(['from' => 'bot'], $message);
            }
        }

        return [
            'transcript' => $transcript,
            'variables' => $session->variables(),
            'awaiting' => $session->awaiting(),
            'paused_until' => $session->pausedUntil?->toIso8601String(),
        ];
    }
}
