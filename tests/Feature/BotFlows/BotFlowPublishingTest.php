<?php

namespace Tests\Feature\BotFlows;

use App\Models\BotFlow;
use App\Models\FacebookPage;
use Database\Factories\BotFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotFlowPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_creates_a_version_and_marks_it_current(): void
    {
        $flow = $this->ownedFlow();

        $this->postJson("/api/bot-flows/{$flow->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.has_unpublished_changes', false)
            ->assertJsonPath('data.published_version.version', 1);

        $flow->refresh();

        $this->assertSame(1, $flow->versions()->count());
        $this->assertSame($flow->versions()->first()->id, $flow->published_version_id);
    }

    public function test_editing_after_publishing_marks_unpublished_changes(): void
    {
        $flow = $this->ownedFlow();
        $this->postJson("/api/bot-flows/{$flow->id}/publish")->assertOk();

        $definition = BotFlowFactory::sampleDefinition();
        $definition['nodes'][1]['data']['text'] = 'Updated prices';

        $this->putJson("/api/bot-flows/{$flow->id}", ['flow_json' => $definition])
            ->assertOk()
            ->assertJsonPath('data.has_unpublished_changes', true)
            ->assertJsonPath('data.published_version.version', 1);

        $this->postJson("/api/bot-flows/{$flow->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.published_version.version', 2)
            ->assertJsonPath('data.has_unpublished_changes', false);
    }

    public function test_publishing_is_refused_while_errors_remain(): void
    {
        $definition = BotFlowFactory::sampleDefinition();
        $definition['nodes'][1]['data']['text'] = '';
        $flow = $this->ownedFlow($definition);

        $this->postJson("/api/bot-flows/{$flow->id}/publish")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'flow.not_publishable')
            ->assertJsonStructure(['errors' => ['flow_json']]);

        $this->assertFalse($flow->refresh()->isPublished());
    }

    public function test_warnings_do_not_block_publishing(): void
    {
        $definition = BotFlowFactory::sampleDefinition();
        $definition['nodes'][] = ['id' => 'orphan', 'type' => 'message', 'position' => ['x' => 600, 'y' => 0], 'data' => ['text' => 'never reached']];
        $definition['edges'][] = ['id' => 'e9', 'source' => 'orphan', 'source_handle' => 'next', 'target' => 'done'];
        $flow = $this->ownedFlow($definition);

        $this->postJson("/api/bot-flows/{$flow->id}/publish")->assertOk();

        $codes = array_column($this->getJson("/api/bot-flows/{$flow->id}")->json('data.issues'), 'code');

        $this->assertContains('unreachable_node', $codes);
    }

    public function test_versions_are_listed_and_can_be_restored(): void
    {
        $flow = $this->ownedFlow();
        $this->postJson("/api/bot-flows/{$flow->id}/publish");

        $updated = BotFlowFactory::sampleDefinition();
        $updated['nodes'][1]['data']['text'] = 'Second version text';
        $this->putJson("/api/bot-flows/{$flow->id}", ['flow_json' => $updated]);
        $this->postJson("/api/bot-flows/{$flow->id}/publish");

        $this->getJson("/api/bot-flows/{$flow->id}/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.is_current', false);

        $this->postJson("/api/bot-flows/{$flow->id}/versions/1/restore")
            ->assertOk()
            ->assertJsonPath('data.flow_json.nodes.1.data.text', 'Our prices start from 10 USD.')
            ->assertJsonPath('data.has_unpublished_changes', true)
            ->assertJsonPath('data.published_version.version', 2);

        $this->postJson("/api/bot-flows/{$flow->id}/versions/99/restore")->assertNotFound();
    }

    public function test_publishing_reports_problems_in_arabic(): void
    {
        $definition = BotFlowFactory::sampleDefinition();
        $definition['nodes'][1]['data']['text'] = '';
        $flow = $this->ownedFlow($definition);

        $this->postJson("/api/bot-flows/{$flow->id}/publish", [], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ما زالت في هذا التدفق مشاكل يجب إصلاحها قبل النشر.')
            ->assertJsonPath('errors.flow_json.0', 'هذه الخطوة تحتاج نص رسالة.');
    }

    private function ownedFlow(?array $definition = null): BotFlow
    {
        $client = $this->actingAsClient();

        return BotFlow::factory()->create([
            'facebook_page_id' => FacebookPage::factory()->for($client)->create()->id,
            'flow_json' => $definition ?? BotFlowFactory::sampleDefinition(),
        ]);
    }
}
