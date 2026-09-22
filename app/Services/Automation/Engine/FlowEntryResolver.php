<?php

namespace App\Services\Automation\Engine;

use App\Enums\CatalogSource;
use App\Enums\FlowInputExpectation;
use App\Enums\FlowNodeType;
use App\Support\BotFlows\FlowDefinition;

class FlowEntryResolver
{
    public function __construct(
        private readonly KeywordMatcher $keywords,
        private readonly InputCapture $input,
    ) {
    }

    public function resolve(FlowDefinition $definition, FlowSession $session, string $text, ?array $payloadTarget = null): FlowResolution
    {
        $awaiting = $session->awaiting();

        if ($payloadTarget !== null) {
            return $this->resolvePayload($definition, $session, $payloadTarget);
        }

        if (($awaiting['kind'] ?? null) === 'choice') {
            return $this->resolveChoice($definition, $session, $text);
        }

        if (($awaiting['kind'] ?? null) === 'input') {
            return $this->resolveInput($definition, $session, $text);
        }

        return $this->resolveTrigger($definition, $text);
    }

    private function resolvePayload(FlowDefinition $definition, FlowSession $session, array $payloadTarget): FlowResolution
    {
        $node = $definition->node($payloadTarget['node_id']);
        $handle = (string) $payloadTarget['handle'];

        if ($node !== null && str_starts_with($handle, 'more:')) {
            $session->setVariable(FlowExecutor::OFFSET_VARIABLE, (string) (int) substr($handle, 5));

            return FlowResolution::startAt($node);
        }

        $session->clearFlowState();

        if ($node !== null && str_starts_with($handle, 'selected:')) {
            $this->rememberSelection($session, $node, substr($handle, 9));

            return FlowResolution::startAt($definition->targetOf($node->id, 'selected'));
        }

        return FlowResolution::startAt($definition->targetOf($payloadTarget['node_id'], $handle));
    }

    private function rememberSelection(FlowSession $session, $node, string $itemId): void
    {
        $source = CatalogSource::tryFrom((string) $node->get('source', '')) ?? CatalogSource::Products;

        $session->setVariable($source->variableName(), $itemId);
        $session->setVariable(FlowExecutor::OFFSET_VARIABLE, '0');

        if ($source === CatalogSource::Categories) {
            $session->setVariable('brand_id', '');
            $session->setVariable('product_id', '');
        }

        if ($source === CatalogSource::Brands) {
            $session->setVariable('product_id', '');
        }
    }

    public function matchesTrigger(FlowDefinition $definition, string $text): bool
    {
        return $this->keywords->matches($text, $definition->entry->triggers, $definition->entry->matchType);
    }

    private function resolveTrigger(FlowDefinition $definition, string $text): FlowResolution
    {
        return $this->matchesTrigger($definition, $text)
            ? FlowResolution::startAt($definition->startNode())
            : FlowResolution::none();
    }

    private function resolveChoice(FlowDefinition $definition, FlowSession $session, string $text): FlowResolution
    {
        $node = $definition->node($this->currentNodeId($session));

        if ($node === null) {
            return FlowResolution::none();
        }

        $choices = match ($node->type) {
            FlowNodeType::Buttons => $node->buttons(),
            FlowNodeType::Cards => $node->postbackChoices(),
            default => $node->options(),
        };

        foreach ($choices as $choice) {
            if ($this->keywords->sameText($text, (string) ($choice['label'] ?? ''))) {
                $session->clearFlowState();

                return FlowResolution::startAt($definition->targetOf($node->id, (string) ($choice['id'] ?? '')));
            }
        }

        $fallback = $definition->targetOf($node->id, FlowNodeType::FALLBACK_HANDLE);

        return $fallback === null ? FlowResolution::none() : FlowResolution::startAt($fallback);
    }

    private function resolveInput(FlowDefinition $definition, FlowSession $session, string $text): FlowResolution
    {
        $awaiting = $session->awaiting() ?? [];
        $node = $definition->node($this->currentNodeId($session));

        if ($node === null) {
            return FlowResolution::none();
        }

        $expects = FlowInputExpectation::tryFrom((string) ($awaiting['expects'] ?? '')) ?? FlowInputExpectation::Text;
        $value = $this->input->capture($text, $expects);

        if ($value !== null) {
            $session->setVariable((string) ($awaiting['variable'] ?? ''), $value);
            $session->clearFlowState();

            return FlowResolution::startAt($definition->targetOf($node->id, 'success'));
        }

        $retriesLeft = (int) ($awaiting['retries_left'] ?? 0);

        if ($retriesLeft > 0) {
            $session->waitFor(
                array_merge($awaiting, ['retries_left' => $retriesLeft - 1]),
                (int) $session->currentFlowId(),
                $session->currentVersionId(),
                $node->id,
            );

            return FlowResolution::retry((string) ($node->get('retry_text') ?: $node->text()));
        }

        $session->clearFlowState();
        $failure = $definition->targetOf($node->id, 'failure');

        return $failure === null ? FlowResolution::handled() : FlowResolution::startAt($failure);
    }

    private function currentNodeId(FlowSession $session): ?string
    {
        return $session->currentNodeId();
    }
}
