<?php

namespace App\Services\Catalog;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductPost;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CatalogService
{
    public function paginateCategories(User $user, ListQuery $listQuery): LengthAwarePaginator
    {
        return $this->paginate($this->ownedQuery($user, ProductCategory::class), $listQuery);
    }

    public function paginateBrands(User $user, ListQuery $listQuery): LengthAwarePaginator
    {
        return $this->paginate($this->ownedQuery($user, ProductBrand::class), $listQuery);
    }

    public function paginateProducts(User $user, ListQuery $listQuery): LengthAwarePaginator
    {
        $products = $this->ownedQuery($user, Product::class)->with(['category', 'brand', 'posts']);

        if ($listQuery->hasFilter('product_category_id')) {
            $products->where('product_category_id', (int) $listQuery->filter('product_category_id'));
        }

        if ($listQuery->hasFilter('product_brand_id')) {
            $products->where('product_brand_id', (int) $listQuery->filter('product_brand_id'));
        }

        if ($listQuery->hasFilter('in_stock')) {
            $products->where('in_stock', (bool) $listQuery->filter('in_stock'));
        }

        return $this->paginate($products, $listQuery);
    }

    public function find(User $user, string $modelClass, string $id): Model
    {
        return $this->ownedQuery($user, $modelClass)->findOrFail($id);
    }

    public function findProduct(User $user, string $id): Product
    {
        return $this->ownedQuery($user, Product::class)->with(['category', 'brand', 'posts'])->findOrFail($id);
    }

    public function create(string $modelClass, array $attributes): Model
    {
        return $modelClass::query()->create($attributes);
    }

    public function update(Model $record, array $attributes): Model
    {
        $record->update($attributes);

        return $record;
    }

    public function delete(Model $record): void
    {
        $record->delete();
    }

    public function linkPost(Product $product, string $postId): ProductPost
    {
        $existing = ProductPost::query()->where('post_id', $postId)->first();

        if ($existing !== null && $existing->product_id !== $product->id) {
            throw new ApiException(ErrorCode::PostAlreadyLinked);
        }

        return ProductPost::query()->updateOrCreate(['post_id' => $postId], ['product_id' => $product->id]);
    }

    public function unlinkPost(Product $product, string $postId): void
    {
        $link = $product->posts()->where('post_id', $postId)->first();

        if ($link === null) {
            throw new ApiException(ErrorCode::ResourceNotFound);
        }

        $link->delete();
    }

    private function ownedQuery(User $user, string $modelClass): Builder
    {
        return $modelClass::query()->whereIn(
            'facebook_page_id',
            $user->facebookPages()->select('facebook_pages.id'),
        );
    }

    private function paginate(Builder $query, ListQuery $listQuery): LengthAwarePaginator
    {
        if ($listQuery->hasFilter('facebook_page_id')) {
            $query->where('facebook_page_id', (int) $listQuery->filter('facebook_page_id'));
        }

        if ($listQuery->hasFilter('is_active')) {
            $query->where('is_active', (bool) $listQuery->filter('is_active'));
        }

        if ($listQuery->hasSearch()) {
            $query->where('name', 'like', $listQuery->searchPattern());
        }

        return $listQuery->paginate($query);
    }
}
