<?php

namespace Tests\Feature\BotFlows;

use App\Models\BotFlow;
use App\Models\FacebookPage;
use Database\Factories\BotFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotFlowSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_walks_the_draft_without_touching_facebook(): void
    {
        Http::fake();
        $flow = $this->ownedFlow();

        $response = $this->postJson("/api/bot-flows/{$flow->id}/simulate", ['messages' => ['menu', 'Prices']])->assertOk();

        $transcript = $response->json('data.transcript');

        $this->assertSame(['customer', 'bot', 'customer', 'bot'], array_column($transcript, 'from'));
        $this->assertSame('Welcome! How can we help?', $transcript[1]['text']);
        $this->assertSame(['Prices', 'Location'], $transcript[1]['quick_replies']);
        $this->assertSame('Our prices start from 10 USD.', $transcript[3]['text']);
        Http::assertNothingSent();
    }

    public function test_simulation_reports_unmatched_messages(): void
    {
        $flow = $this->ownedFlow();

        $this->postJson("/api/bot-flows/{$flow->id}/simulate", ['messages' => ['hello there']])
            ->assertOk()
            ->assertJsonPath('data.transcript.1.from', 'system')
            ->assertJsonPath('data.transcript.1.code', 'no_match');
    }

    public function test_simulation_captures_variables_and_follows_conditions(): void
    {
        $flow = $this->ownedFlow($this->leadCaptureDefinition());

        $response = $this->postJson("/api/bot-flows/{$flow->id}/simulate", [
            'messages' => ['start', 'Layla', 'not-a-phone', '0912345678'],
        ])->assertOk();

        $transcript = $response->json('data.transcript');
        $botTexts = array_column(array_filter($transcript, fn (array $line) => $line['from'] === 'bot'), 'text');

        $this->assertSame('ما اسمك؟', $botTexts[0]);
        $this->assertSame('ما رقم هاتفك؟', $botTexts[1]);
        $this->assertSame('الرقم غير صحيح، حاول مجدداً.', $botTexts[2]);
        $this->assertSame('شكراً Layla، سنتواصل معك على 0912345678', $botTexts[3]);
        $this->assertSame(['name' => 'Layla', 'phone' => '0912345678'], $response->json('data.variables'));
    }

    public function test_simulation_validates_the_messages_payload(): void
    {
        $flow = $this->ownedFlow();

        $this->postJson("/api/bot-flows/{$flow->id}/simulate", ['messages' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('messages');

        $this->postJson("/api/bot-flows/{$flow->id}/simulate", ['messages' => array_fill(0, 21, 'menu')])
            ->assertJsonValidationErrors('messages');
    }

    private function leadCaptureDefinition(): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['start'], 'match_type' => 'exact', 'start_node' => 'ask_name'],
            'nodes' => [
                ['id' => 'ask_name', 'type' => 'ask', 'position' => ['x' => 0, 'y' => 0], 'data' => [
                    'text' => 'ما اسمك؟', 'variable' => 'name', 'expects' => 'text', 'retries' => 1,
                ]],
                ['id' => 'ask_phone', 'type' => 'ask', 'position' => ['x' => 0, 'y' => 200], 'data' => [
                    'text' => 'ما رقم هاتفك؟', 'variable' => 'phone', 'expects' => 'phone', 'retries' => 2,
                    'retry_text' => 'الرقم غير صحيح، حاول مجدداً.',
                ]],
                ['id' => 'check', 'type' => 'condition', 'position' => ['x' => 0, 'y' => 400], 'data' => [
                    'variable' => 'phone', 'operator' => 'is_set',
                ]],
                ['id' => 'thanks', 'type' => 'message', 'position' => ['x' => 0, 'y' => 600], 'data' => [
                    'text' => 'شكراً {{name}}، سنتواصل معك على {{phone}}',
                ]],
                ['id' => 'sorry', 'type' => 'message', 'position' => ['x' => 320, 'y' => 600], 'data' => [
                    'text' => 'لم نستلم رقماً صحيحاً.',
                ]],
                ['id' => 'done', 'type' => 'end', 'position' => ['x' => 0, 'y' => 800], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'ask_name', 'source_handle' => 'success', 'target' => 'ask_phone'],
                ['id' => 'e2', 'source' => 'ask_phone', 'source_handle' => 'success', 'target' => 'check'],
                ['id' => 'e3', 'source' => 'ask_phone', 'source_handle' => 'failure', 'target' => 'sorry'],
                ['id' => 'e4', 'source' => 'check', 'source_handle' => 'true', 'target' => 'thanks'],
                ['id' => 'e5', 'source' => 'check', 'source_handle' => 'false', 'target' => 'sorry'],
                ['id' => 'e6', 'source' => 'thanks', 'source_handle' => 'next', 'target' => 'done'],
                ['id' => 'e7', 'source' => 'sorry', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
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
