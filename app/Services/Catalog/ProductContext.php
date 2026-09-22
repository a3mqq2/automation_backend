<?php

namespace App\Services\Catalog;

use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductPost;

class ProductContext
{
    public const PLACEHOLDER_PREFIX = 'product.';

    public function forVariables(FacebookPage $page, array $variables): array
    {
        $productId = $variables['product_id'] ?? null;

        if ($productId === null) {
            return [];
        }

        $product = Product::query()
            ->where('facebook_page_id', $page->id)
            ->active()
            ->find($productId);

        return $product === null ? [] : $this->forProduct($product);
    }

    public function forPost(FacebookPage $page, ?string $postId): array
    {
        $product = $this->productForPost($page, $postId);

        return $product === null ? [] : $this->forProduct($product);
    }

    public function productForPost(FacebookPage $page, ?string $postId): ?Product
    {
        if ($postId === null || $postId === '') {
            return null;
        }

        $link = ProductPost::query()->where('post_id', $postId)->with('product')->first();
        $product = $link?->product;

        return $product !== null && $product->is_active && $product->facebook_page_id === $page->id ? $product : null;
    }

    public function forProduct(Product $product): array
    {
        return [
            'product.id' => (string) $product->id,
            'product.name' => (string) $product->name,
            'product.sku' => (string) $product->sku,
            'product.price' => $product->formattedPrice(),
            'product.currency' => (string) $product->currency,
            'product.description' => (string) $product->description,
            'product.url' => (string) $product->product_url,
            'product.image_url' => (string) $product->image_url,
        ];
    }

    public function isRequiredBy(?string ...$texts): bool
    {
        foreach ($texts as $text) {
            if ($text !== null && str_contains($text, '{{'.self::PLACEHOLDER_PREFIX)) {
                return true;
            }
        }

        return false;
    }
}
