<?php

namespace Tests\Feature\Rules;

use App\Enums\TriggerType;
use App\Models\AutomationRule;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_a_comment_rule(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();

        $this->postJson('/api/rules', [
            'facebook_page_id' => $page->id,
            'name' => 'Price questions',
            'trigger_type' => 'comment',
            'match_type' => 'contains',
            'keywords' => [' price ', 'السعر', '', 'cost'],
            'response_text' => 'Check your inbox!',
            'private_reply_text' => 'Our prices start at 10 USD.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Price questions')
            ->assertJsonPath('data.keywords', ['price', 'السعر', 'cost'])
            ->assertJsonPath('data.facebook_page.id', $page->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.private_reply_text', 'Our prices start at 10 USD.');

        $this->assertSame(1, $client->automationRules()->count());
    }

    public function test_catch_all_rule_stores_no_keywords(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $this->postJson('/api/rules', [
            'facebook_page_id' => $page->id,
            'name' => 'Greeting',
            'trigger_type' => 'message',
            'match_type' => 'any',
            'keywords' => ['ignored'],
            'response_text' => 'Hello!',
        ])->assertCreated()->assertJsonPath('data.keywords', []);
    }

    public function test_rule_validation_rules(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $base = [
            'facebook_page_id' => $page->id,
            'name' => 'Rule',
            'trigger_type' => 'comment',
            'match_type' => 'contains',
            'keywords' => ['price'],
            'response_text' => 'Reply',
        ];

        $this->postJson('/api/rules', array_merge($base, ['keywords' => []]))->assertJsonValidationErrors('keywords');
        $this->postJson('/api/rules', array_merge($base, ['keywords' => ['a', 'A']]))->assertJsonValidationErrors('keywords.1');
        $this->postJson('/api/rules', array_merge($base, ['trigger_type' => 'story']))->assertJsonValidationErrors('trigger_type');
        $this->postJson('/api/rules', array_merge($base, ['match_type' => 'regex']))->assertJsonValidationErrors('match_type');
        $this->postJson('/api/rules', array_merge($base, ['response_text' => null]))->assertJsonValidationErrors('response_text');
        $this->postJson('/api/rules', array_merge($base, ['response_text' => str_repeat('a', 2001)]))->assertJsonValidationErrors('response_text');
        $this->postJson('/api/rules', array_merge($base, ['trigger_type' => 'message', 'private_reply_text' => 'Nope']))
            ->assertJsonValidationErrors('private_reply_text');
        $this->postJson('/api/rules', array_merge($base, ['trigger_type' => 'message', 'response_text' => null]))
            ->assertJsonValidationErrors('response_text');
        $this->postJson('/api/rules', array_merge($base, ['response_text' => null, 'private_reply_text' => 'DM only']))
            ->assertCreated();

        $this->assertSame(1, AutomationRule::query()->count());
    }

    public function test_rule_page_must_belong_to_the_client_and_be_connected(): void
    {
        $client = $this->actingAsClient();
        $disconnected = FacebookPage::factory()->disconnected()->for($client)->create();
        $foreign = FacebookPage::factory()->for(User::factory()->create())->create();
        $payload = ['name' => 'Rule', 'trigger_type' => 'comment', 'match_type' => 'any', 'response_text' => 'Hi'];

        $this->postJson('/api/rules', array_merge($payload, ['facebook_page_id' => $foreign->id]))
            ->assertJsonValidationErrors('facebook_page_id');
        $this->postJson('/api/rules', array_merge($payload, ['facebook_page_id' => $disconnected->id]))
            ->assertJsonValidationErrors('facebook_page_id');
    }

    public function test_rules_are_listed_filtered_and_sorted(): void
    {
        $client = $this->actingAsClient();
        $shop = FacebookPage::factory()->for($client)->create();
        $cafe = FacebookPage::factory()->for($client)->create();
        AutomationRule::factory()->create(['facebook_page_id' => $shop->id, 'name' => 'Bravo']);
        AutomationRule::factory()->forMessages()->create(['facebook_page_id' => $shop->id, 'name' => 'Alpha']);
        AutomationRule::factory()->inactive()->create(['facebook_page_id' => $cafe->id, 'name' => 'Charlie']);
        AutomationRule::factory()->create(['name' => 'Foreign']);

        $this->getJson('/api/rules?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.0.facebook_page.id', $shop->id)
            ->assertJsonPath('meta.total', 3);

        $this->getJson("/api/rules?facebook_page_id={$cafe->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Charlie');
        $this->getJson('/api/rules?trigger_type=message')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Alpha');
        $this->getJson('/api/rules?is_active=false')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Charlie');
        $this->getJson('/api/rules?is_active=true')->assertJsonCount(2, 'data');
        $this->getJson('/api/rules?search=brav')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bravo');
        $this->getJson('/api/rules?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
    }

    public function test_client_can_view_update_and_delete_a_rule(): void
    {
        $client = $this->actingAsClient();
        $rule = AutomationRule::factory()->withPrivateReply()->create([
            'facebook_page_id' => FacebookPage::factory()->for($client)->create()->id,
        ]);

        $this->getJson("/api/rules/{$rule->id}")->assertOk()->assertJsonPath('data.id', $rule->id);

        $this->patchJson("/api/rules/{$rule->id}", ['name' => 'Renamed', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.keywords', $rule->keywords)
            ->assertJsonPath('data.private_reply_text', $rule->private_reply_text);

        $this->deleteJson("/api/rules/{$rule->id}")->assertNoContent();
        $this->assertModelMissing($rule);
    }

    public function test_update_validates_against_the_merged_rule(): void
    {
        $client = $this->actingAsClient();
        $rule = AutomationRule::factory()->create([
            'facebook_page_id' => FacebookPage::factory()->for($client)->create()->id,
            'response_text' => null,
            'private_reply_text' => 'Private only',
        ]);

        $this->patchJson("/api/rules/{$rule->id}", ['trigger_type' => 'message'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('response_text');

        $this->patchJson("/api/rules/{$rule->id}", ['trigger_type' => 'message', 'response_text' => 'Hello'])
            ->assertOk()
            ->assertJsonPath('data.trigger_type', 'message')
            ->assertJsonPath('data.private_reply_text', null);

        $this->assertSame(TriggerType::Message, $rule->refresh()->trigger_type);
        $this->assertNull($rule->private_reply_text);
    }

    public function test_rule_on_a_disconnected_page_can_still_be_edited(): void
    {
        $client = $this->actingAsClient();
        $rule = AutomationRule::factory()->create([
            'facebook_page_id' => FacebookPage::factory()->disconnected()->for($client)->create()->id,
        ]);

        $this->patchJson("/api/rules/{$rule->id}", ['is_active' => false])->assertOk();
    }

    public function test_rule_cannot_be_moved_to_another_clients_page(): void
    {
        $client = $this->actingAsClient();
        $rule = AutomationRule::factory()->create(['facebook_page_id' => FacebookPage::factory()->for($client)->create()->id]);
        $foreign = FacebookPage::factory()->for(User::factory()->create())->create();

        $this->patchJson("/api/rules/{$rule->id}", ['facebook_page_id' => $foreign->id])
            ->assertJsonValidationErrors('facebook_page_id');
    }

    public function test_other_clients_rules_are_not_accessible(): void
    {
        $this->actingAsClient();
        $foreign = AutomationRule::factory()->create();

        $this->getJson("/api/rules/{$foreign->id}")->assertNotFound()->assertJsonPath('code', 'resource.not_found');
        $this->patchJson("/api/rules/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/rules/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/rules/not-a-number')->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_rules_require_an_active_subscription(): void
    {
        $this->actingAsClient(User::factory()->create());

        $this->getJson('/api/rules')->assertForbidden()->assertJsonPath('code', 'subscription.inactive');
        $this->postJson('/api/rules', [])->assertForbidden();
    }
}
