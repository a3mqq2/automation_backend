<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ConnectedPageListRequest;
use App\Http\Resources\Client\ConnectedPageResource;
use App\Services\Pages\ConnectedPageQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConnectedPageController extends Controller
{
    public function __construct(private readonly ConnectedPageQueryService $connectedPages)
    {
    }

    public function index(ConnectedPageListRequest $request): AnonymousResourceCollection
    {
        return ConnectedPageResource::collection($this->connectedPages->paginate($request->user(), $request->listQuery()));
    }

    public function show(Request $request, string $pageId): ConnectedPageResource
    {
        return new ConnectedPageResource($this->connectedPages->detail($request->user(), $pageId));
    }
}
