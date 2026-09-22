<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FacebookCallbackRequest;
use App\Http\Resources\Client\ProfileResource;
use App\Services\Auth\ClientLoginService;
use App\Services\Auth\ClientProfileService;
use Illuminate\Http\JsonResponse;

class FacebookAuthController extends Controller
{
    public function __construct(
        private readonly ClientLoginService $clientLogin,
        private readonly ClientProfileService $profiles,
    ) {
    }

    public function redirect(): JsonResponse
    {
        return response()->json(['data' => ['url' => $this->clientLogin->authorizationUrl()]]);
    }

    public function callback(FacebookCallbackRequest $request): JsonResponse
    {
        $session = $this->clientLogin->completeLogin($request->validated('state'), $request->validated('error'));

        return response()->json([
            'token' => $session->token,
            'user' => new ProfileResource($this->profiles->profile($session->account)),
        ]);
    }
}
