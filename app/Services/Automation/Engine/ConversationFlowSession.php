<?php

namespace App\Services\Automation\Engine;

use App\Models\Conversation;
use Carbon\CarbonInterface;

class ConversationFlowSession extends FlowSession
{
    public function __construct(public readonly Conversation $conversation)
    {
    }

    public function variables(): array
    {
        return (array) ($this->conversation->variables ?? []);
    }

    public function setVariable(string $name, string $value): void
    {
        $this->conversation->forceFill([
            'variables' => array_merge($this->variables(), [$name => $value]),
        ])->save();
    }

    public function awaiting(): ?array
    {
        return $this->conversation->awaiting;
    }

    public function currentNodeId(): ?string
    {
        return $this->conversation->current_node_id;
    }

    public function currentFlowId(): ?int
    {
        return $this->conversation->bot_flow_id;
    }

    public function currentVersionId(): ?int
    {
        return $this->conversation->bot_flow_version_id;
    }

    public function waitFor(array $awaiting, int $flowId, ?int $versionId, string $nodeId): void
    {
        $this->conversation->forceFill([
            'bot_flow_id' => $flowId,
            'bot_flow_version_id' => $versionId,
            'current_node_id' => $nodeId,
            'awaiting' => $awaiting,
        ])->save();
    }

    public function clearFlowState(): void
    {
        $this->conversation->forceFill([
            'bot_flow_id' => null,
            'bot_flow_version_id' => null,
            'current_node_id' => null,
            'awaiting' => null,
        ])->save();
    }

    public function pauseAutomation(CarbonInterface $until): void
    {
        $this->conversation->forceFill(['automation_paused_until' => $until])->save();
    }
}
