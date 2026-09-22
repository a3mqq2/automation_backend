<?php

namespace App\Services\Automation\Engine;

use Carbon\CarbonInterface;

class SimulatedFlowSession extends FlowSession
{
    private array $variables = [];

    private ?array $awaiting = null;

    private ?int $flowId = null;

    private ?int $versionId = null;

    private ?string $nodeId = null;

    public ?CarbonInterface $pausedUntil = null;

    public function variables(): array
    {
        return $this->variables;
    }

    public function setVariable(string $name, string $value): void
    {
        $this->variables[$name] = $value;
    }

    public function awaiting(): ?array
    {
        return $this->awaiting;
    }

    public function currentNodeId(): ?string
    {
        return $this->nodeId;
    }

    public function currentFlowId(): ?int
    {
        return $this->flowId;
    }

    public function currentVersionId(): ?int
    {
        return $this->versionId;
    }

    public function waitFor(array $awaiting, int $flowId, ?int $versionId, string $nodeId): void
    {
        $this->awaiting = $awaiting;
        $this->flowId = $flowId;
        $this->versionId = $versionId;
        $this->nodeId = $nodeId;
    }

    public function clearFlowState(): void
    {
        $this->awaiting = null;
        $this->flowId = null;
        $this->versionId = null;
        $this->nodeId = null;
    }

    public function pauseAutomation(CarbonInterface $until): void
    {
        $this->pausedUntil = $until;
    }
}
