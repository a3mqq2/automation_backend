<?php

namespace Tests\Feature\BotFlows;

use App\Models\BotFlow;
use App\Models\FacebookPage;
use App\Models\User;
use Database\Factories\BotFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_a_flow(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $this->postJson('/api/bot-flows', $this->payload($page))
            ->assertCreated()
            ->assertJsonPath('data.name', 'القائمة الرئيسية')
            ->assertJsonPath('data.nodes_count', 4)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.has_unpublished_changes', true)
            ->assertJsonPath('data.flow_json.entry.start_node', 'welcome')
            ->assertJsonPath('data.flow_json.nodes.0.type', 'quick_replies')
            ->assertJsonPath('data.flow_json.edges.0.source_handle', 'o1')
            ->assertJsonPath('data.facebook_page.id', $page->id);
    }

    public function test_definition_is_normalized_on_save(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();
        $payload = $this->payload($page);
        $payload['flow_json']['nodes'][0]['unknown_key'] = 'dropped';
        $payload['flow_json']['edges'][0]['id'] = null;

        $this->postJson('/api/bot-flows', $payload)
            ->assertCreated()
            ->assertJsonMissingPath('data.flow_json.nodes.0.unknown_key')
            ->assertJsonPath('data.flow_json.version', 2)
            ->assertJsonPath('data.flow_json.edges.0.id', 'welcome-o1-prices');
    }

    public function test_structural_problems_block_saving(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $this->postJson('/api/bot-flows', $this->payloadWith($page, function (array $flow) {
            $flow['nodes'][1]['type'] = 'teleport';

            return $flow;
        }))->assertUnprocessable()->assertJsonValidationErrors('flow_json');

        $this->postJson('/api/bot-flows', $this->payloadWith($page, function (array $flow) {
            $flow['edges'][] = ['id' => 'x', 'source' => 'welcome', 'source_handle' => 'o9', 'target' => 'prices'];

            return $flow;
        }))->assertUnprocessable()->assertJsonValidationErrors('flow_json');

        $this->postJson('/api/bot-flows', $this->payloadWith($page, function (array $flow) {
            $flow['edges'][] = ['id' => 'x', 'source' => 'prices', 'source_handle' => 'next', 'target' => 'ghost'];

            return $flow;
        }))->assertUnprocessable()->assertJsonValidationErrors('flow_json');

        $this->assertSame(0, BotFlow::query()->count());
    }

    public function test_incomplete_flows_can_be_saved_as_drafts_and_report_issues(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $flowId = $this->postJson('/api/bot-flows', $this->payloadWith($page, function (array $flow) {
            $flow['nodes'][1]['data']['text'] = '';
            $flow['edges'] = array_slice($flow['edges'], 0, 1);

            return $flow;
        }))->assertCreated()->json('data.id');

        $response = $this->getJson("/api/bot-flows/{$flowId}")->assertOk();
        $codes = array_column($response->json('data.issues'), 'code');

        $this->assertContains('missing_text', $codes);
        $this->assertContains('unconnected_choice', $codes);
        $this->assertContains('unreachable_node', $codes);
        $this->assertSame('prices', $response->json('data.issues.0.node_id'));
    }

    public function test_messenger_limits_are_reported_as_issues(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $flowId = $this->postJson('/api/bot-flows', $this->payloadWith($page, function (array $flow) {
            $flow['nodes'][0]['data']['options'] = array_map(
                fn (int $index) => ['id' => 'o'.$index, 'label' => 'Option '.$index],
                range(1, 14),
            );

            return $flow;
        }))->assertCreated()->json('data.id');

        $codes = array_column($this->getJson("/api/bot-flows/{$flowId}")->json('data.issues'), 'code');

        $this->assertContains('too_many_options', $codes);
    }

    public function test_a_loop_without_a_waiting_node_is_an_error(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $flowId = $this->postJson('/api/bot-flows', $this->payloadWith($page, function (array $flow) {
            $flow['nodes'][] = ['id' => 'loop', 'type' => 'message', 'position' => ['x' => 0, 'y' => 600], 'data' => ['text' => 'again']];
            $flow['edges'][2] = ['id' => 'e3', 'source' => 'prices', 'source_handle' => 'next', 'target' => 'loop'];
            $flow['edges'][] = ['id' => 'e6', 'source' => 'loop', 'source_handle' => 'next', 'target' => 'prices'];

            return $flow;
        }))->assertCreated()->json('data.id');

        $issues = $this->getJson("/api/bot-flows/{$flowId}")->json('data.issues');

        $this->assertContains('instant_loop', array_column($issues, 'code'));
    }

    public function test_flow_page_must_belong_to_the_client_and_be_connected(): void
    {
        $client = $this->actingAsClient();

        $this->postJson('/api/bot-flows', $this->payload(FacebookPage::factory()->for(User::factory()->create())->create()))
            ->assertJsonValidationErrors('facebook_page_id');
        $this->postJson('/api/bot-flows', $this->payload(FacebookPage::factory()->disconnected()->for($client)->create()))
            ->assertJsonValidationErrors('facebook_page_id');
    }

    public function test_flows_are_listed_filtered_and_scoped(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        BotFlow::factory()->published()->create(['facebook_page_id' => $page->id, 'name' => 'Welcome']);
        BotFlow::factory()->inactive()->create(['facebook_page_id' => $page->id, 'name' => 'Old menu']);
        BotFlow::factory()->create(['name' => 'Foreign']);

        $this->getJson('/api/bot-flows?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Old menu')
            ->assertJsonPath('data.1.is_published', true)
            ->assertJsonPath('data.1.published_version.version', 1);

        $this->getJson('/api/bot-flows?is_active=0')->assertJsonCount(1, 'data');
        $this->getJson('/api/bot-flows?search=welc')->assertJsonCount(1, 'data');
        $this->getJson("/api/bot-flows?facebook_page_id={$page->id}")->assertJsonCount(2, 'data');
    }

    public function test_client_can_update_and_delete_a_flow(): void
    {
        $client = $this->actingAsClient();
        $flow = BotFlow::factory()->create(['facebook_page_id' => FacebookPage::factory()->for($client)->create()->id]);

        $this->putJson("/api/bot-flows/{$flow->id}", ['name' => 'Renamed', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.nodes_count', 4);

        $this->deleteJson("/api/bot-flows/{$flow->id}")->assertNoContent();
        $this->assertModelMissing($flow);
    }

    public function test_other_clients_flows_are_not_accessible(): void
    {
        $this->actingAsClient();
        $foreign = BotFlow::factory()->create();

        $this->getJson("/api/bot-flows/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/bot-flows/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/bot-flows/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/bot-flows/{$foreign->id}/publish")->assertNotFound();
        $this->postJson("/api/bot-flows/{$foreign->id}/simulate", ['messages' => ['menu']])->assertNotFound();

        $this->assertModelExists($foreign);
    }

    private function payload(FacebookPage $page): array
    {
        return [
            'facebook_page_id' => $page->id,
            'name' => 'القائمة الرئيسية',
            'flow_json' => BotFlowFactory::sampleDefinition(),
        ];
    }

    private function payloadWith(FacebookPage $page, callable $mutate): array
    {
        $payload = $this->payload($page);
        $payload['flow_json'] = $mutate($payload['flow_json']);

        return $payload;
    }
}
