<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CatalogListRequest;
use App\Http\Requests\Client\StoreCatalogGroupRequest;
use App\Http\Resources\Client\CatalogGroupResource;
use App\Models\ProductCategory;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    public function index(CatalogListRequest $request): AnonymousResourceCollection
    {
        return CatalogGroupResource::collection($this->catalog->paginateCategories($request->user(), $request->listQuery()));
    }

    public function store(StoreCatalogGroupRequest $request): CatalogGroupResource
    {
        return new CatalogGroupResource($this->catalog->create(ProductCategory::class, $request->validated()));
    }

    public function show(Request $request, string $category): CatalogGroupResource
    {
        return new CatalogGroupResource($this->catalog->find($request->user(), ProductCategory::class, $category));
    }

    public function update(StoreCatalogGroupRequest $request, string $category): CatalogGroupResource
    {
        $record = $this->catalog->find($request->user(), ProductCategory::class, $category);

        return new CatalogGroupResource($this->catalog->update($record, $request->validated()));
    }

    public function destroy(Request $request, string $category): Response
    {
        $this->catalog->delete($this->catalog->find($request->user(), ProductCategory::class, $category));

        return response()->noContent();
    }
}
