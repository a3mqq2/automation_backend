<?php

namespace App\Services\Demo;

use App\Enums\TriggerType;
use App\Exceptions\ApiException;
use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductPost;
use App\Services\Automation\BotFlowPublisher;
use App\Services\Pages\PagePostReader;
use Illuminate\Support\Facades\DB;

class ElectronicsDemoSeeder
{
    public function __construct(
        private readonly BotFlowPublisher $publisher,
        private readonly PagePostReader $posts,
    ) {
    }

    public function seed(FacebookPage $page, bool $fresh, bool $linkPosts): DemoSeedResult
    {
        if ($fresh) {
            $this->clear($page);
        }

        $products = DB::transaction(fn (): array => $this->seedCatalog($page));
        $offerProducts = array_values(array_map(fn (string $sku) => $products[$sku], ElectronicsCatalog::OFFER_SKUS));

        $postLinkingError = null;
        $linkedPosts = [];

        if ($linkPosts) {
            try {
                $linkedPosts = $this->linkRecentPosts($page, $offerProducts);
            } catch (ApiException $exception) {
                $postLinkingError = $exception->getMessage();
            }
        }

        $hasProductPosts = ProductPost::query()
            ->whereIn('product_id', array_map(fn (Product $product) => $product->id, $products))
            ->exists();

        $flows = DB::transaction(function () use ($page, $offerProducts, $hasProductPosts): array {
            $this->seedRules($page, $hasProductPosts);

            return $this->seedFlows($page, $offerProducts);
        });

        return new DemoSeedResult(
            page: $page,
            categories: count(ElectronicsCatalog::CATEGORIES),
            brands: count(ElectronicsCatalog::BRANDS),
            products: count($products),
            commentRules: count(ElectronicsRules::comments($hasProductPosts)),
            messageRules: count(ElectronicsRules::messages()),
            flows: $flows,
            linkedPosts: $linkedPosts,
            postLinkingError: $postLinkingError,
        );
    }

    private function clear(FacebookPage $page): void
    {
        DB::transaction(function () use ($page): void {
            $page->conversations()->delete();
            $page->automationRules()->delete();
            $page->botFlows()->delete();
            Product::query()->where('facebook_page_id', $page->id)->delete();
            ProductCategory::query()->where('facebook_page_id', $page->id)->delete();
            ProductBrand::query()->where('facebook_page_id', $page->id)->delete();
        });
    }

    private function seedCatalog(FacebookPage $page): array
    {
        $categories = [];
        $sortOrder = 0;

        foreach (ElectronicsCatalog::CATEGORIES as $key => $category) {
            $categories[$key] = ProductCategory::query()->updateOrCreate(
                ['facebook_page_id' => $page->id, 'name' => $category['name']],
                [
                    'image_url' => ElectronicsCatalog::imageUrl($category['label'], $category['color']),
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ],
            );
        }

        $brands = [];
        $sortOrder = 0;

        foreach (ElectronicsCatalog::BRANDS as $key => $name) {
            $brands[$key] = ProductBrand::query()->updateOrCreate(
                ['facebook_page_id' => $page->id, 'name' => $name],
                [
                    'image_url' => ElectronicsCatalog::imageUrl($name, '111827'),
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ],
            );
        }

        $products = [];
        $sortOrder = 0;

        foreach (ElectronicsCatalog::PRODUCTS as $product) {
            $color = ElectronicsCatalog::CATEGORIES[$product['category']]['color'];
            $products[$product['sku']] = Product::query()->updateOrCreate(
                ['facebook_page_id' => $page->id, 'sku' => $product['sku']],
                [
                    'product_category_id' => $categories[$product['category']]->id,
                    'product_brand_id' => $brands[$product['brand']]->id,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'currency' => ElectronicsCatalog::CURRENCY,
                    'description' => $product['description'],
                    'image_url' => ElectronicsCatalog::imageUrl($product['name'], $color),
                    'product_url' => 'https://www.facebook.com/'.$page->page_id,
                    'in_stock' => $product['in_stock'] ?? true,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ],
            );
        }

        return $products;
    }

    private function linkRecentPosts(FacebookPage $page, array $offerProducts): array
    {
        $linked = [];
        $candidates = array_values(array_filter(
            $this->posts->recent($page->user, $page->page_id),
            fn (array $post) => $post['post_id'] !== ''
                && ($post['linked_product'] === null || in_array($post['linked_product']['id'], array_map(fn (Product $product) => $product->id, $offerProducts), true)),
        ));

        foreach (array_slice($candidates, 0, count($offerProducts)) as $index => $post) {
            $product = $offerProducts[$index];
            ProductPost::query()->updateOrCreate(['post_id' => $post['post_id']], ['product_id' => $product->id]);
            $linked[] = [
                'post_id' => $post['post_id'],
                'message' => (string) ($post['message'] ?? ''),
                'permalink_url' => $post['permalink_url'] ?? null,
                'product' => $product->name,
            ];
        }

        return $linked;
    }

    private function seedRules(FacebookPage $page, bool $hasProductPosts): void
    {
        foreach (ElectronicsRules::comments($hasProductPosts) as $rule) {
            $this->upsertRule($page, TriggerType::Comment, $rule);
        }

        foreach (ElectronicsRules::messages() as $rule) {
            $this->upsertRule($page, TriggerType::Message, $rule + ['private_reply_text' => null, 'is_active' => true]);
        }
    }

    private function upsertRule(FacebookPage $page, TriggerType $triggerType, array $rule): void
    {
        AutomationRule::query()->updateOrCreate(
            ['facebook_page_id' => $page->id, 'name' => $rule['name']],
            [
                'trigger_type' => $triggerType,
                'match_type' => $rule['match_type'],
                'keywords' => $rule['keywords'],
                'response_text' => $rule['response_text'],
                'private_reply_text' => $rule['private_reply_text'],
                'is_active' => $rule['is_active'],
            ],
        );
    }

    private function seedFlows(FacebookPage $page, array $offerProducts): array
    {
        $mainMenu = $this->flowNamed($page, ElectronicsFlowBlueprints::MAIN_MENU);
        $tracking = $this->flowNamed($page, ElectronicsFlowBlueprints::ORDER_TRACKING);
        $maintenance = $this->flowNamed($page, ElectronicsFlowBlueprints::MAINTENANCE);
        $handoff = $this->flowNamed($page, ElectronicsFlowBlueprints::HUMAN_HANDOFF);

        $definitions = [
            [$handoff, ElectronicsFlowBlueprints::humanHandoff(), ElectronicsFlowBlueprints::HUMAN_HANDOFF_TRIGGERS],
            [$tracking, ElectronicsFlowBlueprints::orderTracking($mainMenu->id), ElectronicsFlowBlueprints::ORDER_TRACKING_TRIGGERS],
            [$maintenance, ElectronicsFlowBlueprints::maintenance($mainMenu->id), ElectronicsFlowBlueprints::MAINTENANCE_TRIGGERS],
            [$mainMenu, ElectronicsFlowBlueprints::mainMenu($page, $offerProducts, $tracking->id, $maintenance->id), ElectronicsFlowBlueprints::MAIN_MENU_TRIGGERS],
        ];

        foreach ($definitions as [$flow, $definition]) {
            $flow->forceFill(['flow_json' => $definition, 'is_active' => true])->save();
        }

        return array_map(function (array $entry): array {
            [$flow, , $triggers] = $entry;
            $version = $this->publisher->publish($flow->refresh());

            return [
                'id' => $flow->id,
                'name' => $flow->name,
                'version' => $version->version,
                'nodes' => count($flow->flow_json['nodes']),
                'triggers' => $triggers,
            ];
        }, array_reverse($definitions));
    }

    private function flowNamed(FacebookPage $page, string $name): BotFlow
    {
        return BotFlow::query()->firstOrCreate(
            ['facebook_page_id' => $page->id, 'name' => $name],
            ['flow_json' => ElectronicsFlowBlueprints::humanHandoff(), 'is_active' => true],
        );
    }
}
