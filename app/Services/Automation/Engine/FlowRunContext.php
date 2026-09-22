<?php

namespace App\Services\Automation\Engine;

use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use App\Models\FacebookPage;
use App\Services\Automation\Engine\Sinks\MessageSink;
use App\Support\BotFlows\FlowDefinition;

class FlowRunContext
{
    public array $executedNodes = [];

    public function __construct(
        public BotFlow $flow,
        public BotFlowVersion $version,
        public FlowDefinition $definition,
        public readonly MessageSink $sink,
        public readonly FlowSession $session,
        public readonly string $psid,
        public readonly string $incomingText,
        public readonly ?FacebookPage $page = null,
        public readonly bool $simulating = false,
    ) {
    }

    public function switchTo(BotFlow $flow, BotFlowVersion $version): void
    {
        $this->flow = $flow;
        $this->version = $version;
        $this->definition = $version->definition();
    }

    public function recordNode(string $nodeId, string $type, array $details = []): void
    {
        $this->executedNodes[] = array_merge(['node_id' => $nodeId, 'type' => $type], $details);
    }
}
