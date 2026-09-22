<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientListRequest;
use App\Http\Resources\Admin\ClientDetailResource;
use App\Http\Resources\Admin\ClientResource;
use App\Models\User;
use App\Services\Admin\ClientDirectoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function __construct(private readonly ClientDirectoryService $clients)
    {
    }

    public function index(ClientListRequest $request): AnonymousResourceCollection
    {
        return ClientResource::collection($this->clients->paginate($request->listQuery()));
    }

    public function show(User $client): ClientDetailResource
    {
        return new ClientDetailResource($this->clients->detail($client));
    }
}
