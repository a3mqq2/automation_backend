<?php

namespace Tests\Feature\Catalog;

use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class ProductCommentReplyTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    private FacebookPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = FacebookPage::factory()->create(['page_id' => '4040', 'page_access_token' => 'page-token']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'reply_1'])]);
    }

    public function test_one_rule_answers_every_post_with_its_own_product_price(): void
    {
        $this->priceRule();
        $playstation = $this->linkedProduct('PlayStation 5 Pro', 11300, '4040_900');
        $table = $this->linkedProduct('طاولة مكتب', 450, '4040_901');

        $this->comment('4040_900', 'بكم السعر؟');
        $this->comment('4040_901', 'السعر؟');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/900_1/comments')
            && $request['message'] === 'سعر PlayStation 5 Pro هو 11300 د.ل');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/901_1/comments')
            && $request['message'] === 'سعر طاولة مكتب هو 450 د.ل');

        $logs = ActivityLog::query()->orderBy('id')->get();

        $this->assertSame($playstation->id, $logs[0]->payload['product_id']);
        $this->assertSame($table->id, $logs[1]->payload['product_id']);
    }

    public function test_a_post_without_a_linked_product_is_skipped_by_product_rules(): void
    {
        $this->priceRule();

        $this->comment('4040_999', 'السعر؟');

        Http::assertNothingSent();
        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_a_plain_rule_still_answers_posts_without_products(): void
    {
        AutomationRule::factory()->create([
            'facebook_page_id' => $this->page->id,
            'keywords' => ['السعر'],
            'response_text' => 'راسلنا على الخاص لمعرفة السعر',
            'private_reply_text' => null,
        ]);

        $this->comment('4040_999', 'السعر؟');

        Http::assertSent(fn (Request $request) => $request['message'] === 'راسلنا على الخاص لمعرفة السعر');
    }

    public function test_the_private_reply_can_carry_the_product_link(): void
    {
        AutomationRule::factory()->create([
            'facebook_page_id' => $this->page->id,
            'keywords' => ['السعر'],
            'response_text' => 'أرسلنا لك التفاصيل على الخاص',
            'private_reply_text' => '{{product.name}} بسعر {{product.price}} {{product.currency}}: {{product.url}}',
        ]);
        $this->linkedProduct('PlayStation 5 Pro', 11300, '4040_900');

        $this->comment('4040_900', 'كم السعر؟');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/4040/messages')
            && $request['message'] === ['text' => 'PlayStation 5 Pro بسعر 11300 د.ل: https://store.example.com/ps5']);
    }

    private function priceRule(): void
    {
        AutomationRule::factory()->create([
            'facebook_page_id' => $this->page->id,
            'keywords' => ['السعر'],
            'response_text' => 'سعر {{product.name}} هو {{product.price}} {{product.currency}}',
            'private_reply_text' => null,
        ]);
    }

    private function linkedProduct(string $name, float $price, string $postId): Product
    {
        $product = Product::factory()->create([
            'facebook_page_id' => $this->page->id,
            'name' => $name,
            'price' => $price,
            'currency' => 'د.ل',
            'product_url' => 'https://store.example.com/ps5',
        ]);

        ProductPost::factory()->create(['product_id' => $product->id, 'post_id' => $postId]);

        return $product;
    }

    private function comment(string $postId, string $message): void
    {
        $this->postSignedWebhook($this->commentPayload('4040', [
            'post_id' => $postId,
            'comment_id' => explode('_', $postId)[1].'_1',
            'message' => $message,
        ]))->assertOk();
    }
}
