<?php

namespace App\Services\Automation\Engine;

use App\Enums\CatalogSource;
use App\Enums\FlowInputExpectation;
use App\Enums\FlowNodeType;
use App\Jobs\ResumeFlowNode;
use App\Models\BotFlow;
use App\Services\Catalog\CatalogBrowser;
use App\Services\Catalog\CatalogItem;
use App\Services\Catalog\ProductContext;
use App\Support\BotFlows\FlowNode;

class FlowExecutor
{
    public const MAX_NODES_PER_TURN = 25;

    public const OFFSET_VARIABLE = 'catalog_offset';

    public function __construct(
        private readonly ConditionEvaluator $conditions,
        private readonly TextInterpolator $interpolator,
        private readonly CatalogBrowser $catalog,
        private readonly ProductContext $products,
    ) {
    }

    public function run(FlowRunContext $context, ?FlowNode $node): void
    {
        $steps = 0;

        while ($node !== null && $steps < self::MAX_NODES_PER_TURN) {
            $steps++;
            $node = $this->executeNode($context, $node);
        }
    }

    private function executeNode(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        return match ($node->type) {
            FlowNodeType::Message => $this->executeMessage($context, $node),
            FlowNodeType::QuickReplies => $this->executeQuickReplies($context, $node),
            FlowNodeType::Buttons => $this->executeButtons($context, $node),
            FlowNodeType::Cards => $this->executeCards($context, $node),
            FlowNodeType::Catalog => $this->executeCatalog($context, $node),
            FlowNodeType::Ask => $this->executeAsk($context, $node),
            FlowNodeType::Condition => $this->executeCondition($context, $node),
            FlowNodeType::Delay => $this->executeDelay($context, $node),
            FlowNodeType::SetVariable => $this->executeSetVariable($context, $node),
            FlowNodeType::Jump => $this->executeJump($context, $node),
            FlowNodeType::Handoff => $this->executeHandoff($context, $node),
            FlowNodeType::End => $this->executeEnd($context, $node),
        };
    }

    private function executeMessage(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $text = $this->text($context, $node);

        if ($node->imageUrl() !== null) {
            $context->sink->sendImage($node->imageUrl());
        }

        if ($text !== '' || $node->imageUrl() === null) {
            $context->sink->sendText($text);
        }

        $context->recordNode($node->id, $node->type->value, ['text' => $text, 'image_url' => $node->imageUrl()]);

        return $context->definition->targetOf($node->id, FlowNodeType::NEXT_HANDLE);
    }

    private function executeQuickReplies(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $text = $this->text($context, $node);
        $quickReplies = array_map(fn (array $option) => [
            'label' => (string) $option['label'],
            'payload' => $this->payload($context, $node->id, (string) $option['id']),
        ], $node->options());

        if ($node->imageUrl() !== null) {
            $context->sink->sendImage($node->imageUrl());
        }

        $context->sink->sendText($text, $quickReplies);
        $context->recordNode($node->id, $node->type->value, ['text' => $text, 'image_url' => $node->imageUrl()]);
        $context->session->waitFor(['kind' => 'choice'], $context->flow->id, $context->version->id, $node->id);

        return null;
    }

    private function executeButtons(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $text = $this->text($context, $node);
        $buttons = array_map(fn (array $button) => [
            'label' => (string) $button['label'],
            'type' => (string) ($button['type'] ?? 'next'),
            'url' => (string) ($button['url'] ?? ''),
            'payload' => $this->payload($context, $node->id, (string) ($button['id'] ?? '')),
        ], $node->buttons());

        $context->sink->sendButtons($text, $buttons);
        $context->recordNode($node->id, $node->type->value, ['text' => $text]);
        $context->session->waitFor(['kind' => 'choice'], $context->flow->id, $context->version->id, $node->id);

        return null;
    }

    private function executeCards(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $variables = $this->variables($context);
        $cards = array_map(fn (array $element) => [
            'title' => $this->interpolator->render((string) ($element['title'] ?? ''), $variables),
            'subtitle' => $this->interpolator->render((string) ($element['subtitle'] ?? ''), $variables),
            'image_url' => (string) ($element['image_url'] ?? ''),
            'buttons' => array_map(fn (array $button) => [
                'label' => (string) $button['label'],
                'type' => (string) ($button['type'] ?? 'next'),
                'url' => (string) ($button['url'] ?? ''),
                'payload' => $this->payload($context, $node->id, (string) ($button['id'] ?? '')),
            ], array_values(array_filter((array) ($element['buttons'] ?? []), 'is_array'))),
        ], $node->elements());

        $quickReplies = array_map(fn (array $option) => [
            'label' => (string) $option['label'],
            'payload' => $this->payload($context, $node->id, (string) $option['id']),
        ], $node->options());

        $context->sink->sendCards($cards, $quickReplies);
        $context->recordNode($node->id, $node->type->value, ['cards' => count($cards)]);
        $context->session->waitFor(['kind' => 'choice'], $context->flow->id, $context->version->id, $node->id);

        return null;
    }

    private function executeCatalog(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        if ($context->page === null) {
            return $context->definition->targetOf($node->id, 'empty');
        }

        $source = CatalogSource::tryFrom((string) $node->get('source', '')) ?? CatalogSource::Products;
        $variables = $context->session->variables();
        $offset = (int) ($variables[self::OFFSET_VARIABLE] ?? 0);
        $limit = (int) $node->get('limit', 10);
        $locale = $context->definition->entry->locale;
        $page = $this->catalog->page($context->page, $source, $this->catalogFilters($variables), $offset, $limit, $locale);

        $context->recordNode($node->id, $node->type->value, [
            'source' => $source->value,
            'offset' => $offset,
            'shown' => count($page->items),
            'total' => $page->total,
        ]);

        if ($page->isEmpty()) {
            $context->session->setVariable(self::OFFSET_VARIABLE, '0');

            return $context->definition->targetOf($node->id, 'empty');
        }

        $selectLabel = trim((string) $node->get('select_label', '')) ?: __('catalog.select', [], $locale);
        $cards = array_map(fn (CatalogItem $item) => [
            'title' => $item->title,
            'subtitle' => $item->subtitle,
            'image_url' => (string) $item->imageUrl,
            'buttons' => array_values(array_filter([
                $item->url === null || $item->url === '' ? null : [
                    'label' => trim((string) $node->get('link_label', '')) ?: __('catalog.select', [], $locale),
                    'type' => 'url',
                    'url' => $item->url,
                    'payload' => '',
                ],
                [
                    'label' => $selectLabel,
                    'type' => 'next',
                    'url' => '',
                    'payload' => $this->payload($context, $node->id, 'selected:'.$item->id),
                ],
            ])),
        ], $page->items);

        $quickReplies = [];
        $nextOffset = $page->nextOffset();

        if ($nextOffset !== null) {
            $quickReplies[] = [
                'label' => trim((string) $node->get('more_label', '')) ?: __('catalog.more', [], $locale),
                'payload' => $this->payload($context, $node->id, 'more:'.$nextOffset),
            ];
        }

        $context->sink->sendText($this->text($context, $node));
        $context->sink->sendCards($cards, $quickReplies);
        $context->session->setVariable(self::OFFSET_VARIABLE, (string) $offset);
        $context->session->waitFor(['kind' => 'choice'], $context->flow->id, $context->version->id, $node->id);

        return null;
    }

    private function catalogFilters(array $variables): array
    {
        return array_filter([
            'category_id' => $variables['category_id'] ?? null,
            'brand_id' => $variables['brand_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function executeAsk(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $text = $this->text($context, $node);
        $context->sink->sendText($text);
        $context->recordNode($node->id, $node->type->value, ['text' => $text]);
        $context->session->waitFor([
            'kind' => 'input',
            'variable' => (string) $node->get('variable'),
            'expects' => (string) $node->get('expects', FlowInputExpectation::Text->value),
            'retries_left' => (int) $node->get('retries', 1),
        ], $context->flow->id, $context->version->id, $node->id);

        return null;
    }

    private function executeCondition(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $result = $this->conditions->evaluate($node, $context->session);
        $context->recordNode($node->id, $node->type->value, ['result' => $result]);

        return $context->definition->targetOf($node->id, $result ? 'true' : 'false');
    }

    private function executeDelay(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $next = $context->definition->targetOf($node->id, FlowNodeType::NEXT_HANDLE);
        $context->recordNode($node->id, $node->type->value, ['seconds' => (int) $node->get('seconds', 1)]);

        if ($context->simulating) {
            return $next;
        }

        if ($next !== null && $context->page !== null) {
            ResumeFlowNode::dispatch(
                $context->page->id,
                $context->psid,
                $context->flow->id,
                $context->version->id,
                $next->id,
            )->delay(now()->addSeconds((int) $node->get('seconds', 1)));
        }

        $context->session->clearFlowState();

        return null;
    }

    private function executeSetVariable(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $value = $this->interpolator->render((string) $node->get('value', ''), $this->variables($context));
        $context->session->setVariable((string) $node->get('variable'), $value);
        $context->recordNode($node->id, $node->type->value, ['variable' => (string) $node->get('variable'), 'value' => $value]);

        return $context->definition->targetOf($node->id, FlowNodeType::NEXT_HANDLE);
    }

    private function executeJump(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $targetFlow = BotFlow::query()
            ->runnable()
            ->where('facebook_page_id', $context->flow->facebook_page_id)
            ->with('publishedVersion')
            ->find((int) $node->get('bot_flow_id'));

        $context->recordNode($node->id, $node->type->value, ['bot_flow_id' => $node->get('bot_flow_id')]);

        if ($targetFlow?->publishedVersion === null) {
            $context->session->clearFlowState();

            return null;
        }

        $context->switchTo($targetFlow, $targetFlow->publishedVersion);

        return $context->definition->startNode();
    }

    private function executeHandoff(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $text = $this->text($context, $node);

        if ($text !== '') {
            $context->sink->sendText($text);
        }

        $minutes = (int) $node->get('pause_minutes', 1440);
        $context->session->pauseAutomation(now()->addMinutes($minutes));
        $context->session->clearFlowState();
        $context->recordNode($node->id, $node->type->value, ['pause_minutes' => $minutes]);

        return null;
    }

    private function executeEnd(FlowRunContext $context, FlowNode $node): ?FlowNode
    {
        $context->session->clearFlowState();
        $context->recordNode($node->id, $node->type->value);

        return null;
    }

    private function text(FlowRunContext $context, FlowNode $node): string
    {
        return $this->interpolator->render($node->text(), $this->variables($context));
    }

    private function variables(FlowRunContext $context): array
    {
        $variables = $context->session->variables();

        if ($context->page === null) {
            return $variables;
        }

        return array_merge($variables, $this->products->forVariables($context->page, $variables));
    }

    private function payload(FlowRunContext $context, string $nodeId, string $handle): string
    {
        return "FLOW:{$context->flow->id}:{$nodeId}:{$handle}";
    }
}
