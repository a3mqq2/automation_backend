<?php

namespace Tests\Feature\Demo;

use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPost;
use App\Models\User;
use App\Services\Automation\BotFlowPublisher;
use App\Services\Demo\ElectronicsFlowBlueprints;
use App\Support\BotFlows\FlowIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElectronicsDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_complete_published_automation_on_the_first_connected_page(): void
    {
        [$client, $page] = $this->clientWithPages();
        $this->fakePosts($page, ['4040_1', '4040_2', '4040_3', '4040_4']);

        $this->artisan('demo:electronics', ['client' => $client->id])->assertSuccessful();

        $this->assertSame(5, ProductCategory::query()->where('facebook_page_id', $page->id)->count());
        $this->assertSame(20, Product::query()->where('facebook_page_id', $page->id)->count());
        $this->assertSame(12, AutomationRule::query()->where('facebook_page_id', $page->id)->count());

        $flows = BotFlow::query()->where('facebook_page_id', $page->id)->get();

        $this->assertCount(4, $flows);

        foreach ($flows as $flow) {
            $this->assertTrue($flow->isPublished(), "{$flow->name} is not published");
            $this->assertSame([], array_map(
                fn (FlowIssue $issue) => $issue->code.':'.$issue->nodeId,
                app(BotFlowPublisher::class)->issues($flow),
            ), "{$flow->name} has issues");
        }

        $this->assertSame(
            ['Galaxy S25 Ultra 256GB', 'AirPods Pro 2', 'Redmi Note 14 Pro 256GB'],
            ProductPost::query()->whereIn('post_id', ['4040_1', '4040_2', '4040_3'])->orderBy('post_id')->with('product')->get()
                ->map(fn (ProductPost $link) => $link->product->name)->all(),
        );
        $this->assertFalse(ProductPost::query()->where('post_id', '4040_4')->exists());
        $this->assertStringContainsString(
            '{{product.name}}',
            AutomationRule::query()->where('name', 'تعليقات: السعر')->value('private_reply_text'),
        );
    }

    public function test_the_main_menu_walks_through_an_order_in_the_simulator(): void
    {
        [$client, $page] = $this->clientWithPages();
        $this->artisan('demo:electronics', ['client' => $client->id, '--skip-posts' => true])->assertSuccessful();
        $mainMenu = BotFlow::query()->where('name', ElectronicsFlowBlueprints::MAIN_MENU)->firstOrFail();
        $this->actingAsClient($client);

        $transcript = $this->postJson("/api/bot-flows/{$mainMenu->id}/simulate", [
            'messages' => ['القائمة', '🔥 عروض الأسبوع', 'اطلبها الآن', 'Omar Ali', '0912345678', 'طرابلس', '✅ تأكيد الطلب'],
        ])->assertOk()->json('data.transcript');

        $botTexts = implode("\n", array_filter(array_column(
            array_filter($transcript, fn (array $line) => $line['from'] === 'bot'),
            'text',
        )));

        $this->assertStringContainsString("مرحباً بك في {$page->name}", $botTexts);
        $this->assertStringContainsString('لإتمام طلب Galaxy S25 Ultra 256GB', $botTexts);
        $this->assertStringContainsString('💰 السعر: 6400 LYD', $botTexts);
        $this->assertStringContainsString('📍 المدينة: طرابلس', $botTexts);
        $this->assertStringContainsString('تم استلام طلبك بنجاح', $botTexts);

        $browsing = $this->postJson("/api/bot-flows/{$mainMenu->id}/simulate", [
            'messages' => ['القائمة', '🛍️ تصفح المنتجات'],
        ])->assertOk()->json('data.transcript');

        $this->assertSame(
            ['هواتف ذكية', 'لابتوبات', 'سماعات وصوتيات', 'ساعات ذكية', 'شواحن وإكسسوارات'],
            array_column(collect($browsing)->firstWhere('type', 'cards')['cards'] ?? [], 'title'),
        );
    }

    public function test_running_it_again_updates_instead_of_duplicating(): void
    {
        [$client, $page] = $this->clientWithPages();

        $this->artisan('demo:electronics', ['client' => $client->id, '--skip-posts' => true])->assertSuccessful();
        $this->artisan('demo:electronics', ['client' => $client->id, '--skip-posts' => true])->assertSuccessful();

        $this->assertSame(20, Product::query()->where('facebook_page_id', $page->id)->count());
        $this->assertSame(12, AutomationRule::query()->where('facebook_page_id', $page->id)->count());
        $this->assertSame(4, BotFlow::query()->where('facebook_page_id', $page->id)->count());
        $this->assertSame(2, BotFlow::query()->where('name', ElectronicsFlowBlueprints::MAIN_MENU)->firstOrFail()->versions()->count());
        $this->assertStringNotContainsString(
            '{{product.name}}',
            AutomationRule::query()->where('name', 'تعليقات: السعر')->value('private_reply_text'),
        );
    }

    public function test_fresh_removes_existing_page_data_and_conversations(): void
    {
        [$client, $page] = $this->clientWithPages();
        $oldRule = AutomationRule::factory()->create(['facebook_page_id' => $page->id]);
        Conversation::factory()->create(['facebook_page_id' => $page->id]);

        $this->artisan('demo:electronics', ['client' => $client->id, '--fresh' => true, '--skip-posts' => true])
            ->expectsConfirmation("This deletes every rule, bot flow, catalog item and conversation on \"{$page->name}\". Continue?", 'yes')
            ->assertSuccessful();

        $this->assertNull(AutomationRule::query()->find($oldRule->id));
        $this->assertSame(0, Conversation::query()->where('facebook_page_id', $page->id)->count());
        $this->assertSame(12, AutomationRule::query()->where('facebook_page_id', $page->id)->count());
    }

    public function test_the_page_option_selects_another_connected_page(): void
    {
        [$client, $first] = $this->clientWithPages();
        $second = FacebookPage::factory()->for($client)->create();

        $this->artisan('demo:electronics', ['client' => $client->id, '--page' => $second->page_id, '--skip-posts' => true])
            ->assertSuccessful();

        $this->assertSame(0, BotFlow::query()->where('facebook_page_id', $first->id)->count());
        $this->assertSame(4, BotFlow::query()->where('facebook_page_id', $second->id)->count());
    }

    public function test_post_reading_failures_fall_back_to_the_general_price_reply(): void
    {
        [$client, $page] = $this->clientWithPages();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Unsupported', 'code' => 100]], 400)]);

        $this->artisan('demo:electronics', ['client' => $client->id])->assertSuccessful();

        $this->assertSame(0, ProductPost::query()->count());
        $this->assertStringNotContainsString(
            '{{product.name}}',
            AutomationRule::query()->where('name', 'تعليقات: السعر')->value('private_reply_text'),
        );
    }

    public function test_it_fails_for_an_unknown_client_or_a_client_without_connected_pages(): void
    {
        $this->artisan('demo:electronics', ['client' => 999])->assertFailed();

        $client = User::factory()->subscribed()->create();
        FacebookPage::factory()->disconnected()->for($client)->create();

        $this->artisan('demo:electronics', ['client' => $client->id])->assertFailed();
        $this->assertSame(0, BotFlow::query()->count());
    }

    private function clientWithPages(): array
    {
        $client = User::factory()->subscribed()->create();
        FacebookPage::factory()->disconnected()->for($client)->create();
        $page = FacebookPage::factory()->for($client)->create(['page_id' => '4040', 'name' => 'Tech Store']);

        return [$client, $page];
    }

    private function fakePosts(FacebookPage $page, array $postIds): void
    {
        Http::fake(["graph.facebook.com/v23.0/{$page->page_id}/posts*" => Http::response([
            'data' => array_map(fn (string $postId) => [
                'id' => $postId,
                'message' => "Post {$postId}",
                'permalink_url' => "https://facebook.com/{$postId}",
                'created_time' => '2026-09-20T10:00:00+0000',
            ], $postIds),
        ])]);
    }
}
