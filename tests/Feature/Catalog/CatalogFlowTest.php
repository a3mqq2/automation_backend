<?php

namespace Tests\Feature\Catalog;

use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Services\Automation\BotFlowPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class CatalogFlowTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    private FacebookPage $page;

    private BotFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = FacebookPage::factory()->create(['page_id' => '6060', 'page_access_token' => 'page-token']);
        Http::fake([
            'graph.facebook.com/v23.0/7770001*' => Http::response(['first_name' => 'Aisha']),
            'graph.facebook.com/*' => Http::response(['message_id' => 'm_out']),
        ]);

        $this->flow = BotFlow::factory()->create([
            'facebook_page_id' => $this->page->id,
            'name' => 'المتجر',
            'flow_json' => $this->storeDefinition(),
        ]);

        app(BotFlowPublisher::class)->publish($this->flow);
        $this->flow->refresh();
    }

    public function test_the_customer_walks_categories_then_brands_then_products(): void
    {
        $electronics = ProductCategory::factory()->create(['facebook_page_id' => $this->page->id, 'name' => 'إلكترونيات']);
        $furniture = ProductCategory::factory()->create(['facebook_page_id' => $this->page->id, 'name' => 'أثاث']);
        $sony = ProductBrand::factory()->create(['facebook_page_id' => $this->page->id, 'name' => 'Sony']);
        $ikea = ProductBrand::factory()->create(['facebook_page_id' => $this->page->id, 'name' => 'IKEA']);
        $playstation = Product::factory()->create([
            'facebook_page_id' => $this->page->id,
            'product_category_id' => $electronics->id,
            'product_brand_id' => $sony->id,
            'name' => 'PlayStation 5 Pro',
            'price' => 11300,
            'currency' => 'د.ل',
            'product_url' => 'https://store.example.com/ps5-pro',
        ]);
        Product::factory()->create([
            'facebook_page_id' => $this->page->id,
            'product_category_id' => $furniture->id,
            'product_brand_id' => $ikea->id,
            'name' => 'طاولة مكتب',
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('المتجر')))->assertOk();
        $this->assertCardTitles(['إلكترونيات', 'أثاث']);

        $this->tapCard('categories', $electronics->id);
        $this->assertCardTitles(['Sony']);

        $this->tapCard('brands', $sony->id);
        $this->assertCardTitles(['PlayStation 5 Pro']);

        Http::assertSent(function (Request $request) {
            $elements = data_get($request->data(), 'message.attachment.payload.elements');

            return $elements !== null
                && ($elements[0]['title'] ?? null) === 'PlayStation 5 Pro'
                && ($elements[0]['subtitle'] ?? null) === 'السعر: 11300 د.ل'
                && ($elements[0]['buttons'][0]['type'] ?? null) === 'web_url'
                && ($elements[0]['buttons'][0]['url'] ?? null) === 'https://store.example.com/ps5-pro';
        });

        $this->tapCard('products', $playstation->id);

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text')
            === 'ممتاز! اخترت PlayStation 5 Pro بسعر 11300 د.ل. أرسل عنوانك لإتمام الطلب.');

        $conversation = Conversation::query()->sole();

        $this->assertSame((string) $electronics->id, $conversation->variables['category_id']);
        $this->assertSame((string) $sony->id, $conversation->variables['brand_id']);
        $this->assertSame((string) $playstation->id, $conversation->variables['product_id']);
    }

    public function test_choosing_a_new_category_clears_the_deeper_choices(): void
    {
        $electronics = ProductCategory::factory()->create(['facebook_page_id' => $this->page->id]);
        $brand = ProductBrand::factory()->create(['facebook_page_id' => $this->page->id]);
        Product::factory()->create([
            'facebook_page_id' => $this->page->id,
            'product_category_id' => $electronics->id,
            'product_brand_id' => $brand->id,
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('المتجر')));
        $this->tapCard('categories', $electronics->id);
        $this->tapCard('brands', $brand->id);
        $this->tapCard('categories', $electronics->id);

        $variables = Conversation::query()->sole()->variables;

        $this->assertSame('', $variables['brand_id']);
        $this->assertSame('', $variables['product_id']);
    }

    public function test_the_more_button_pages_through_a_long_catalog(): void
    {
        ProductCategory::factory()->count(12)->create(['facebook_page_id' => $this->page->id]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('المتجر')));

        $firstPage = $this->lastCardsMessage();

        $this->assertCount(10, $firstPage['attachment']['payload']['elements']);
        $this->assertSame('المزيد', $firstPage['quick_replies'][0]['title']);
        $this->assertSame("FLOW:{$this->flow->id}:categories:more:10", $firstPage['quick_replies'][0]['payload']);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('المزيد', $firstPage['quick_replies'][0]['payload'])));

        $secondPage = $this->lastCardsMessage();

        $this->assertCount(2, $secondPage['attachment']['payload']['elements']);
        $this->assertArrayNotHasKey('quick_replies', $secondPage);
    }

    public function test_an_empty_catalog_follows_the_empty_branch(): void
    {
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('المتجر')))->assertOk();

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'لا توجد أقسام متاحة حالياً.');
        $this->assertMessagesSent(1);
    }

    private function tapCard(string $nodeId, int $itemId): void
    {
        $this->postSignedWebhook($this->messagePayload('6060', [
            'postback' => ['mid' => 'pb_'.$nodeId.'_'.$itemId.'_'.uniqid(), 'title' => 'اختيار', 'payload' => "FLOW:{$this->flow->id}:{$nodeId}:selected:{$itemId}"],
        ]))->assertOk();
    }

    private function lastCardsMessage(): array
    {
        $cardMessages = $this->sentMessages()
            ->map(fn (Request $request) => data_get($request->data(), 'message'))
            ->filter(fn (?array $message) => isset($message['attachment']['payload']['elements']));

        return $cardMessages->last();
    }

    private function assertCardTitles(array $titles): void
    {
        $elements = $this->lastCardsMessage()['attachment']['payload']['elements'];

        $this->assertSame($titles, array_column($elements, 'title'));
    }

    private function storeDefinition(): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['المتجر'], 'match_type' => 'exact', 'start_node' => 'categories'],
            'nodes' => [
                ['id' => 'categories', 'type' => 'catalog', 'position' => ['x' => 0, 'y' => 0], 'data' => [
                    'text' => 'اختر القسم',
                    'source' => 'categories',
                    'limit' => 10,
                    'select_label' => 'اختيار',
                ]],
                ['id' => 'brands', 'type' => 'catalog', 'position' => ['x' => 0, 'y' => 200], 'data' => [
                    'text' => 'اختر الشركة',
                    'source' => 'brands',
                    'limit' => 10,
                    'select_label' => 'اختيار',
                ]],
                ['id' => 'products', 'type' => 'catalog', 'position' => ['x' => 0, 'y' => 400], 'data' => [
                    'text' => 'هذه المنتجات المتاحة',
                    'source' => 'products',
                    'limit' => 10,
                    'select_label' => 'اطلبه الان',
                    'link_label' => 'المتجر',
                ]],
                ['id' => 'confirm', 'type' => 'message', 'position' => ['x' => 0, 'y' => 600], 'data' => [
                    'text' => 'ممتاز! اخترت {{product.name}} بسعر {{product.price}} {{product.currency}}. أرسل عنوانك لإتمام الطلب.',
                ]],
                ['id' => 'empty', 'type' => 'message', 'position' => ['x' => 400, 'y' => 200], 'data' => [
                    'text' => 'لا توجد أقسام متاحة حالياً.',
                ]],
                ['id' => 'done', 'type' => 'end', 'position' => ['x' => 0, 'y' => 800], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'categories', 'source_handle' => 'selected', 'target' => 'brands'],
                ['id' => 'e2', 'source' => 'categories', 'source_handle' => 'empty', 'target' => 'empty'],
                ['id' => 'e3', 'source' => 'brands', 'source_handle' => 'selected', 'target' => 'products'],
                ['id' => 'e4', 'source' => 'brands', 'source_handle' => 'empty', 'target' => 'empty'],
                ['id' => 'e5', 'source' => 'products', 'source_handle' => 'selected', 'target' => 'confirm'],
                ['id' => 'e6', 'source' => 'products', 'source_handle' => 'empty', 'target' => 'empty'],
                ['id' => 'e7', 'source' => 'confirm', 'source_handle' => 'next', 'target' => 'done'],
                ['id' => 'e8', 'source' => 'empty', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
    }
}
