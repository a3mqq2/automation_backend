<?php

namespace App\Services\Automation\Engine;

use Carbon\CarbonInterface;

abstract class FlowSession
{
    abstract public function variables(): array;

    abstract public function setVariable(string $name, string $value): void;

    abstract public function awaiting(): ?array;

    abstract public function currentNodeId(): ?string;

    abstract public function currentFlowId(): ?int;

    abstract public function currentVersionId(): ?int;

    abstract public function waitFor(array $awaiting, int $flowId, ?int $versionId, string $nodeId): void;

    abstract public function clearFlowState(): void;

    abstract public function pauseAutomation(CarbonInterface $until): void;

    public function variable(string $name): ?string
    {
        $value = $this->variables()[$name] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
