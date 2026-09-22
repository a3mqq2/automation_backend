<?php

namespace Tests\Feature\Catalog;

use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_manages_categories_and_brands(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $categoryId = $this->postJson('/api/product-categories', [
            'facebook_page_id' => $page->id,
            'name' => 'إلكترونيات',
            'image_url' => 'https://cdn.example.com/electronics.jpg',
            'sort_order' => 1,
        ])->assertCreated()->assertJsonPath('data.name', 'إلكترونيات')->json('data.id');

        $this->postJson('/api/product-brands', ['facebook_page_id' => $page->id, 'name' => 'Sony'])->assertCreated();

        $this->getJson('/api/product-categories')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/product-brands')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/product-categories/{$categoryId}", [
            'facebook_page_id' => $page->id,
            'name' => 'إلكترونيات ومنزل',
        ])->assertOk()->assertJsonPath('data.name', 'إلكترونيات ومنزل');

        $this->deleteJson("/api/product-categories/{$categoryId}")->assertNoContent();
        $this->assertSame(0, ProductCategory::query()->count());
    }

    public function test_client_manages_products(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $category = ProductCategory::factory()->create(['facebook_page_id' => $page->id]);
        $brand = ProductBrand::factory()->create(['facebook_page_id' => $page->id]);

        $response = $this->postJson('/api/products', [
            'facebook_page_id' => $page->id,
            'product_category_id' => $category->id,
            'product_brand_id' => $brand->id,
            'name' => 'PlayStation 5 Pro',
            'price' => 11300,
            'currency' => 'د.ل',
            'image_url' => 'https://cdn.example.com/ps5.jpg',
            'product_url' => 'https://store.example.com/ps5-pro',
        ])->assertCreated();

        $response->assertJsonPath('data.formatted_price', '11300')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonPath('data.in_stock', true);

        $productId = $response->json('data.id');

        $this->getJson("/api/products?product_category_id={$category->id}")->assertJsonCount(1, 'data');
        $this->patchJson("/api/products/{$productId}", [
            'facebook_page_id' => $page->id,
            'name' => 'PlayStation 5 Pro',
            'price' => 10900,
            'in_stock' => false,
        ])->assertOk()->assertJsonPath('data.formatted_price', '10900')->assertJsonPath('data.in_stock', false);
    }

    public function test_products_cannot_borrow_another_pages_category(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $foreignCategory = ProductCategory::factory()->create();

        $this->postJson('/api/products', [
            'facebook_page_id' => $page->id,
            'product_category_id' => $foreignCategory->id,
            'name' => 'منتج',
        ])->assertUnprocessable()->assertJsonValidationErrors('product_category_id');
    }

    public function test_catalog_is_scoped_to_the_client(): void
    {
        $this->actingAsClient();
        $foreignProduct = Product::factory()->create();

        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$foreignProduct->id}")->assertNotFound();
        $this->deleteJson("/api/products/{$foreignProduct->id}")->assertNotFound();
        $this->assertModelExists($foreignProduct);
    }

    public function test_a_product_is_linked_to_a_post_and_a_post_belongs_to_one_product(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $product = Product::factory()->create(['facebook_page_id' => $page->id]);
        $other = Product::factory()->create(['facebook_page_id' => $page->id]);

        $this->postJson("/api/products/{$product->id}/posts", ['post_id' => '103673872192331_900'])
            ->assertOk()
            ->assertJsonPath('data.post_ids.0', '103673872192331_900');

        $this->postJson("/api/products/{$other->id}/posts", ['post_id' => '103673872192331_900'])
            ->assertConflict()
            ->assertJsonPath('code', 'product.post_already_linked');

        $this->deleteJson("/api/products/{$product->id}/posts", ['post_id' => '103673872192331_900'])->assertNoContent();
        $this->assertSame(0, ProductPost::query()->count());
    }

    public function test_page_posts_are_listed_with_their_linked_product(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create(['page_id' => '4040', 'page_access_token' => 'page-token']);
        $product = Product::factory()->create(['facebook_page_id' => $page->id, 'name' => 'PlayStation 5 Pro']);
        ProductPost::factory()->create(['product_id' => $product->id, 'post_id' => '4040_900']);

        Http::fake(['graph.facebook.com/v23.0/4040/posts*' => Http::response(['data' => [
            ['id' => '4040_900', 'message' => 'عرض البلايستيشن', 'created_time' => '2026-09-20T10:00:00+0000', 'full_picture' => 'https://cdn.example.com/p.jpg'],
            ['id' => '4040_901', 'message' => 'منشور آخر', 'created_time' => '2026-09-19T10:00:00+0000'],
        ]])]);

        $this->getJson("/api/pages/4040/posts")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.linked_product.name', 'PlayStation 5 Pro')
            ->assertJsonPath('data.1.linked_product', null);
    }

    public function test_catalog_requires_an_active_subscription(): void
    {
        $this->actingAsClient(User::factory()->create());

        $this->getJson('/api/products')->assertForbidden()->assertJsonPath('code', 'subscription.inactive');
        $this->getJson('/api/product-categories')->assertForbidden();
    }
}
