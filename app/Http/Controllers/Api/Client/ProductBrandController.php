<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CatalogListRequest;
use App\Http\Requests\Client\StoreCatalogGroupRequest;
use App\Http\Resources\Client\CatalogGroupResource;
use App\Models\ProductBrand;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductBrandController extends Controller
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    public function index(CatalogListRequest $request): AnonymousResourceCollection
    {
        return CatalogGroupResource::collection($this->catalog->paginateBrands($request->user(), $request->listQuery()));
    }

    public function store(StoreCatalogGroupRequest $request): CatalogGroupResource
    {
        return new CatalogGroupResource($this->catalog->create(ProductBrand::class, $request->validated()));
    }

    public function show(Request $request, string $brand): CatalogGroupResource
    {
        return new CatalogGroupResource($this->catalog->find($request->user(), ProductBrand::class, $brand));
    }

    public function update(StoreCatalogGroupRequest $request, string $brand): CatalogGroupResource
    {
        $record = $this->catalog->find($request->user(), ProductBrand::class, $brand);

        return new CatalogGroupResource($this->catalog->update($record, $request->validated()));
    }

    public function destroy(Request $request, string $brand): Response
    {
        $this->catalog->delete($this->catalog->find($request->user(), ProductBrand::class, $brand));

        return response()->noContent();
    }
}
