<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FacebookCallbackRequest;
use App\Http\Resources\Client\ProfileResource;
use App\Services\Auth\ClientProfileService;
use App\Services\Auth\FacebookLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacebookLinkController extends Controller
{
    public function __construct(
        private readonly FacebookLinkService $links,
        private readonly ClientProfileService $profiles,
    ) {
    }

    public function redirect(Request $request): JsonResponse
    {
        return response()->json(['data' => ['url' => $this->links->authorizationUrl($request->user())]]);
    }

    public function store(FacebookCallbackRequest $request): ProfileResource
    {
        $client = $this->links->link($request->user(), $request->validated('state'), $request->validated('error'));

        return new ProfileResource($this->profiles->profile($client));
    }
}
