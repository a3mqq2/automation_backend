<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\LinkProductPostRequest;
use App\Http\Requests\Client\ProductListRequest;
use App\Http\Requests\Client\StoreProductRequest;
use App\Http\Resources\Client\ProductResource;
use App\Models\Product;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    public function index(ProductListRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->catalog->paginateProducts($request->user(), $request->listQuery()));
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $product = $this->catalog->create(Product::class, $request->validated());

        return new ProductResource($product->load(['category', 'brand', 'posts']));
    }

    public function show(Request $request, string $product): ProductResource
    {
        return new ProductResource($this->catalog->findProduct($request->user(), $product));
    }

    public function update(StoreProductRequest $request, string $product): ProductResource
    {
        $record = $this->catalog->findProduct($request->user(), $product);

        return new ProductResource($this->catalog->update($record, $request->validated())->load(['category', 'brand', 'posts']));
    }

    public function destroy(Request $request, string $product): Response
    {
        $this->catalog->delete($this->catalog->findProduct($request->user(), $product));

        return response()->noContent();
    }

    public function linkPost(LinkProductPostRequest $request, string $product): ProductResource
    {
        $record = $this->catalog->findProduct($request->user(), $product);
        $this->catalog->linkPost($record, $request->validated('post_id'));

        return new ProductResource($record->load(['category', 'brand', 'posts']));
    }

    public function unlinkPost(LinkProductPostRequest $request, string $product): Response
    {
        $this->catalog->unlinkPost($this->catalog->findProduct($request->user(), $product), $request->validated('post_id'));

        return response()->noContent();
    }
}
