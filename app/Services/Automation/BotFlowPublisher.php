<?php

namespace App\Services\Automation;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use App\Support\BotFlows\FlowDefinition;
use App\Support\BotFlows\FlowDefinitionValidator;
use App\Support\BotFlows\FlowIssue;
use App\Support\BotFlows\FlowNode;
use Illuminate\Support\Facades\DB;

class BotFlowPublisher
{
    public function __construct(private readonly FlowDefinitionValidator $validator)
    {
    }

    public function issues(BotFlow $flow): array
    {
        return array_merge(
            $this->validator->validate((array) $flow->flow_json),
            $this->jumpIssues($flow),
        );
    }

    public function publish(BotFlow $flow): BotFlowVersion
    {
        $errors = array_values(array_filter(
            $this->issues($flow),
            fn (FlowIssue $issue) => $issue->severity->value === 'error',
        ));

        if ($errors !== []) {
            throw new ApiException(ErrorCode::FlowNotPublishable, [
                'flow_json' => array_map(fn (FlowIssue $issue) => $issue->message(), $errors),
            ]);
        }

        return DB::transaction(function () use ($flow): BotFlowVersion {
            $version = $flow->versions()->create([
                'version' => ((int) $flow->versions()->max('version')) + 1,
                'definition' => $flow->draftDefinition()->toArray(),
                'published_at' => now(),
            ]);

            $flow->forceFill(['published_version_id' => $version->id])->save();

            return $version;
        });
    }

    public function restore(BotFlow $flow, BotFlowVersion $version): BotFlow
    {
        $flow->forceFill(['flow_json' => FlowDefinition::fromArray($version->definition)->toArray()])->save();

        return $flow;
    }

    private function jumpIssues(BotFlow $flow): array
    {
        $issues = [];
        $definition = $flow->draftDefinition();

        foreach ($definition->nodes as $node) {
            if ($node->type->value !== 'jump') {
                continue;
            }

            $issues = array_merge($issues, $this->jumpTargetIssues($flow, $node));
        }

        return $issues;
    }

    private function jumpTargetIssues(BotFlow $flow, FlowNode $node): array
    {
        $target = BotFlow::query()->find((int) $node->get('bot_flow_id'));

        if ($target === null) {
            return [FlowIssue::error('jump_target_missing', $node->id)];
        }

        if ($target->facebook_page_id !== $flow->facebook_page_id) {
            return [FlowIssue::error('jump_target_other_page', $node->id)];
        }

        if (! $target->isPublished() && $target->id !== $flow->id) {
            return [FlowIssue::warning('jump_target_not_published', $node->id)];
        }

        return [];
    }
}
