<?php

namespace Tests\Feature\Webhook;

use App\Jobs\ResumeFlowNode;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Services\Automation\BotFlowPublisher;
use App\Services\Automation\Engine\FlowExecutor;
use App\Services\Automation\Engine\FlowRunner;
use Database\Factories\BotFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class FlowEngineTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    private FacebookPage $page;

    private ?array $graphError = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = FacebookPage::factory()->create(['page_id' => '6060', 'page_access_token' => 'page-token']);
        Http::fake(['graph.facebook.com/*' => fn () => $this->graphError === null
            ? Http::response(['message_id' => 'm_out'])
            : Http::response(['error' => $this->graphError], 400)]);
    }

    public function test_trigger_starts_the_flow_and_pins_the_published_version(): void
    {
        $flow = $this->publish(BotFlowFactory::sampleDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('menu')))->assertOk();

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'Welcome! How can we help?'
            && data_get($request->data(), 'message.quick_replies') === [
                ['content_type' => 'text', 'title' => 'Prices', 'payload' => "FLOW:{$flow->id}:welcome:o1"],
                ['content_type' => 'text', 'title' => 'Location', 'payload' => "FLOW:{$flow->id}:welcome:o2"],
            ]);

        $conversation = Conversation::query()->sole();

        $this->assertSame($flow->id, $conversation->bot_flow_id);
        $this->assertSame($flow->published_version_id, $conversation->bot_flow_version_id);
        $this->assertSame('welcome', $conversation->current_node_id);
        $this->assertSame('choice', $conversation->awaitingKind());

        $log = ActivityLog::query()->sole();

        $this->assertSame('flow_step', $log->event_type->value);
        $this->assertSame('welcome', $log->payload['nodes'][0]['node_id']);
        $this->assertSame(1, $log->payload['flow_version']);
    }

    public function test_quick_reply_payload_advances_and_the_end_node_clears_the_state(): void
    {
        $flow = $this->publish(BotFlowFactory::sampleDefinition());
        $this->startFlow($flow);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('Prices', "FLOW:{$flow->id}:welcome:o1")));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'Our prices start from 10 USD.');

        $conversation = Conversation::query()->sole();

        $this->assertNull($conversation->bot_flow_id);
        $this->assertNull($conversation->awaiting);
    }

    public function test_typing_the_option_label_advances_too(): void
    {
        $flow = $this->publish(BotFlowFactory::sampleDefinition());
        $this->startFlow($flow);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage(' location ')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'We are located downtown.');
    }

    public function test_an_unmatched_reply_follows_the_fallback_edge(): void
    {
        $definition = BotFlowFactory::sampleDefinition();
        $definition['nodes'][] = ['id' => 'help', 'type' => 'message', 'position' => ['x' => 640, 'y' => 200], 'data' => ['text' => 'اختر من الأزرار.']];
        $definition['edges'][] = ['id' => 'ef', 'source' => 'welcome', 'source_handle' => 'fallback', 'target' => 'help'];
        $definition['edges'][] = ['id' => 'eh', 'source' => 'help', 'source_handle' => 'next', 'target' => 'done'];
        $flow = $this->publish($definition);
        $this->startFlow($flow);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('شيء آخر')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'اختر من الأزرار.');
    }

    public function test_an_unmatched_reply_without_fallback_falls_through_to_rules(): void
    {
        $flow = $this->publish(BotFlowFactory::sampleDefinition());
        $this->startFlow($flow);
        AutomationRule::factory()->forMessages()->create([
            'facebook_page_id' => $this->page->id,
            'match_type' => 'any',
            'keywords' => [],
            'response_text' => 'Rule answer',
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('something else')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message') === ['text' => 'Rule answer']);
    }

    public function test_ask_captures_a_variable_and_later_messages_use_it(): void
    {
        $flow = $this->publish($this->leadDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('Layla')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'أهلاً Layla');
        $this->assertSame(['name' => 'Layla'], Conversation::query()->sole()->variables);
    }

    public function test_invalid_input_is_retried_then_sent_to_the_failure_branch(): void
    {
        $this->publish($this->phoneDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('abc')));
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('still not a phone')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'الرقم غير صحيح');
        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'سنتواصل بطريقة أخرى');
        $this->assertNull(Conversation::query()->sole()->awaiting);
    }

    public function test_condition_branches_on_a_variable(): void
    {
        $this->publish($this->phoneDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('0912345678')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'شكراً، رقمك 0912345678');
        $this->assertSame(['phone' => '0912345678'], Conversation::query()->sole()->variables);
    }

    public function test_delay_schedules_a_resume_job_and_the_job_continues_the_flow(): void
    {
        $flow = $this->publish($this->delayDefinition());
        Bus::fake([ResumeFlowNode::class]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));

        Bus::assertDispatched(ResumeFlowNode::class, fn (ResumeFlowNode $job) => $job->nodeId === 'later'
            && $job->botFlowId === $flow->id
            && $job->delay?->diffInSeconds(now(), true) >= 4);

        Bus::assertDispatchedTimes(ResumeFlowNode::class, 1);

        (new ResumeFlowNode($this->page->id, '7770001', $flow->id, (int) $flow->published_version_id, 'later'))
            ->handle(app(FlowRunner::class));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'وصلتك بعد قليل');
    }

    public function test_jump_continues_in_another_published_flow(): void
    {
        $targetDefinition = $this->simpleDefinition('targeted', 'رسالة التدفق الثاني');
        $targetDefinition['entry']['triggers'] = ['second-flow-only'];
        $target = $this->publish($targetDefinition, 'Second');
        $definition = $this->simpleDefinition('start_here', 'أنقلك الآن');
        $definition['nodes'][] = ['id' => 'jump', 'type' => 'jump', 'position' => ['x' => 0, 'y' => 200], 'data' => ['bot_flow_id' => $target->id]];
        $definition['edges'] = [['id' => 'e1', 'source' => 'start_here', 'source_handle' => 'next', 'target' => 'jump']];
        $this->publish($definition);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'أنقلك الآن');
        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'رسالة التدفق الثاني');
    }

    public function test_handoff_pauses_automation_for_the_configured_time(): void
    {
        $definition = $this->simpleDefinition('greet', 'سيتواصل معك موظف');
        $definition['nodes'][1] = ['id' => 'human', 'type' => 'handoff', 'position' => ['x' => 0, 'y' => 200], 'data' => ['pause_minutes' => 120, 'text' => 'حوّلناك لموظف']];
        $definition['edges'] = [['id' => 'e1', 'source' => 'greet', 'source_handle' => 'next', 'target' => 'human']];
        $this->publish($definition);
        AutomationRule::factory()->forMessages()->create([
            'facebook_page_id' => $this->page->id,
            'match_type' => 'any',
            'keywords' => [],
            'response_text' => 'Rule answer',
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));
        $this->assertMessagesSent(2);

        $conversation = Conversation::query()->sole();
        $this->assertTrue($conversation->isAutomationPaused());
        $this->assertTrue($conversation->automation_paused_until->between(now()->addMinutes(118), now()->addMinutes(122)));

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('hello again')));
        $this->assertMessagesSent(2);
    }

    public function test_buttons_are_sent_as_a_template(): void
    {
        $definition = $this->simpleDefinition('greet', 'اختر');
        $definition['nodes'][0] = ['id' => 'greet', 'type' => 'buttons', 'position' => ['x' => 0, 'y' => 0], 'data' => [
            'text' => 'اختر',
            'buttons' => [
                ['id' => 'b1', 'label' => 'المتجر', 'type' => 'url', 'url' => 'https://shop.example.com'],
                ['id' => 'b2', 'label' => 'متابعة', 'type' => 'next'],
            ],
        ]];
        $definition['edges'] = [['id' => 'e1', 'source' => 'greet', 'source_handle' => 'b2', 'target' => 'second']];
        $flow = $this->publish($definition);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.attachment.payload.buttons') === [
            ['type' => 'web_url', 'title' => 'المتجر', 'url' => 'https://shop.example.com'],
            ['type' => 'postback', 'title' => 'متابعة', 'payload' => "FLOW:{$flow->id}:greet:b2"],
        ]);
    }

    public function test_the_turn_stops_after_the_node_limit(): void
    {
        $nodes = [];
        $edges = [];

        for ($index = 1; $index <= 40; $index++) {
            $nodes[] = ['id' => 'n'.$index, 'type' => 'message', 'position' => ['x' => 0, 'y' => $index * 100], 'data' => ['text' => 'message '.$index]];

            if ($index > 1) {
                $edges[] = ['id' => 'e'.$index, 'source' => 'n'.($index - 1), 'source_handle' => 'next', 'target' => 'n'.$index];
            }
        }

        $nodes[] = ['id' => 'finish', 'type' => 'end', 'position' => ['x' => 0, 'y' => 4100], 'data' => []];
        $edges[] = ['id' => 'e_last', 'source' => 'n40', 'source_handle' => 'next', 'target' => 'finish'];

        $this->publish([
            'version' => 2,
            'entry' => ['triggers' => ['start'], 'match_type' => 'exact', 'start_node' => 'n1'],
            'nodes' => $nodes,
            'edges' => $edges,
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('start')));

        $this->assertMessagesSent(FlowExecutor::MAX_NODES_PER_TURN);
    }

    public function test_a_running_conversation_keeps_the_version_it_started_on(): void
    {
        $flow = $this->publish(BotFlowFactory::sampleDefinition());
        $this->startFlow($flow);

        $updated = BotFlowFactory::sampleDefinition();
        $updated['nodes'][1]['data']['text'] = 'New prices text';
        $flow->forceFill(['flow_json' => $updated])->save();
        app(BotFlowPublisher::class)->publish($flow->fresh());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('Prices')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'Our prices start from 10 USD.');
    }

    public function test_a_failed_send_is_logged_and_stops_the_turn(): void
    {
        $this->graphError = ['message' => 'Outside window', 'code' => 10];
        $this->publish(BotFlowFactory::sampleDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('menu')))->assertOk();

        $log = ActivityLog::query()->sole();

        $this->assertSame('failed', $log->status->value);
        $this->assertSame(10, $log->payload['error']['code']);
    }

    private function publish(array $definition, string $name = 'Main'): BotFlow
    {
        $flow = BotFlow::factory()->create([
            'facebook_page_id' => $this->page->id,
            'name' => $name,
            'flow_json' => $definition,
        ]);

        app(BotFlowPublisher::class)->publish($flow);

        return $flow->refresh();
    }

    private function startFlow(BotFlow $flow): void
    {
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('menu')));
    }

    private function simpleDefinition(string $startId, string $text): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['start'], 'match_type' => 'exact', 'start_node' => $startId],
            'nodes' => [
                ['id' => $startId, 'type' => 'message', 'position' => ['x' => 0, 'y' => 0], 'data' => ['text' => $text]],
                ['id' => 'second', 'type' => 'end', 'position' => ['x' => 0, 'y' => 200], 'data' => []],
            ],
            'edges' => [['id' => 'e1', 'source' => $startId, 'source_handle' => 'next', 'target' => 'second']],
        ];
    }

    private function leadDefinition(): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['start'], 'match_type' => 'exact', 'start_node' => 'ask_name'],
            'nodes' => [
                ['id' => 'ask_name', 'type' => 'ask', 'position' => ['x' => 0, 'y' => 0], 'data' => [
                    'text' => 'ما اسمك؟', 'variable' => 'name', 'expects' => 'text', 'retries' => 1,
                ]],
                ['id' => 'greet', 'type' => 'message', 'position' => ['x' => 0, 'y' => 200], 'data' => ['text' => 'أهلاً {{name}}']],
                ['id' => 'done', 'type' => 'end', 'position' => ['x' => 0, 'y' => 400], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'ask_name', 'source_handle' => 'success', 'target' => 'greet'],
                ['id' => 'e2', 'source' => 'greet', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
    }

    private function phoneDefinition(): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['start'], 'match_type' => 'exact', 'start_node' => 'ask_phone'],
            'nodes' => [
                ['id' => 'ask_phone', 'type' => 'ask', 'position' => ['x' => 0, 'y' => 0], 'data' => [
                    'text' => 'رقمك؟', 'variable' => 'phone', 'expects' => 'phone', 'retries' => 1, 'retry_text' => 'الرقم غير صحيح',
                ]],
                ['id' => 'check', 'type' => 'condition', 'position' => ['x' => 0, 'y' => 200], 'data' => [
                    'variable' => 'phone', 'operator' => 'is_set',
                ]],
                ['id' => 'thanks', 'type' => 'message', 'position' => ['x' => 0, 'y' => 400], 'data' => ['text' => 'شكراً، رقمك {{phone}}']],
                ['id' => 'sorry', 'type' => 'message', 'position' => ['x' => 320, 'y' => 400], 'data' => ['text' => 'سنتواصل بطريقة أخرى']],
                ['id' => 'done', 'type' => 'end', 'position' => ['x' => 0, 'y' => 600], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'ask_phone', 'source_handle' => 'success', 'target' => 'check'],
                ['id' => 'e2', 'source' => 'ask_phone', 'source_handle' => 'failure', 'target' => 'sorry'],
                ['id' => 'e3', 'source' => 'check', 'source_handle' => 'true', 'target' => 'thanks'],
                ['id' => 'e4', 'source' => 'check', 'source_handle' => 'false', 'target' => 'sorry'],
                ['id' => 'e5', 'source' => 'thanks', 'source_handle' => 'next', 'target' => 'done'],
                ['id' => 'e6', 'source' => 'sorry', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
    }

    private function delayDefinition(): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['start'], 'match_type' => 'exact', 'start_node' => 'now'],
            'nodes' => [
                ['id' => 'now', 'type' => 'message', 'position' => ['x' => 0, 'y' => 0], 'data' => ['text' => 'لحظة من فضلك']],
                ['id' => 'wait', 'type' => 'delay', 'position' => ['x' => 0, 'y' => 200], 'data' => ['seconds' => 5]],
                ['id' => 'later', 'type' => 'message', 'position' => ['x' => 0, 'y' => 400], 'data' => ['text' => 'وصلتك بعد قليل']],
                ['id' => 'done', 'type' => 'end', 'position' => ['x' => 0, 'y' => 600], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'now', 'source_handle' => 'next', 'target' => 'wait'],
                ['id' => 'e2', 'source' => 'wait', 'source_handle' => 'next', 'target' => 'later'],
                ['id' => 'e3', 'source' => 'later', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
    }
}
