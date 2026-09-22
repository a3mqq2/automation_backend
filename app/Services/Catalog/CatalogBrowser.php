<?php

namespace App\Services\Catalog;

use App\Enums\CatalogSource;
use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;

class CatalogBrowser
{
    public function page(FacebookPage $page, CatalogSource $source, array $filters, int $offset, int $limit, string $locale = 'ar'): CatalogPage
    {
        $query = $this->query($page, $source, $filters);
        $total = (clone $query)->count();
        $records = $query->orderBy('sort_order')->orderBy('id')->skip($offset)->take($limit)->get();

        return new CatalogPage(
            items: $records->map(fn ($record) => $this->toItem($source, $record, $locale))->all(),
            offset: $offset,
            total: $total,
        );
    }

    private function query(FacebookPage $page, CatalogSource $source, array $filters): Builder
    {
        return match ($source) {
            CatalogSource::Categories => ProductCategory::query()
                ->where('facebook_page_id', $page->id)
                ->active(),
            CatalogSource::Brands => ProductBrand::query()
                ->where('facebook_page_id', $page->id)
                ->active()
                ->when(
                    isset($filters['category_id']),
                    fn (Builder $query) => $query->whereHas('products', fn (Builder $products) => $products
                        ->active()
                        ->where('product_category_id', $filters['category_id'])),
                ),
            CatalogSource::Products => Product::query()
                ->where('facebook_page_id', $page->id)
                ->active()
                ->when(isset($filters['category_id']), fn (Builder $query) => $query->where('product_category_id', $filters['category_id']))
                ->when(isset($filters['brand_id']), fn (Builder $query) => $query->where('product_brand_id', $filters['brand_id'])),
        };
    }

    private function toItem(CatalogSource $source, $record, string $locale): CatalogItem
    {
        if ($source !== CatalogSource::Products) {
            return new CatalogItem(
                id: (string) $record->id,
                title: (string) $record->name,
                subtitle: '',
                imageUrl: $record->image_url,
                url: null,
            );
        }

        return new CatalogItem(
            id: (string) $record->id,
            title: (string) $record->name,
            subtitle: $this->productSubtitle($record, $locale),
            imageUrl: $record->image_url,
            url: $record->product_url,
        );
    }

    private function productSubtitle(Product $product, string $locale): string
    {
        $price = $product->price === null
            ? ''
            : __('catalog.price', ['price' => $product->formattedPrice(), 'currency' => $product->currency], $locale);

        if (! $product->in_stock) {
            $price = trim($price.' '.__('catalog.out_of_stock', [], $locale));
        }

        return trim($price !== '' ? $price : (string) $product->description);
    }
}
