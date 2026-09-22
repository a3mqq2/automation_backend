<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\Admin\AdminResource;
use App\Services\Admin\AdminAuthService;
use App\Services\Auth\AccessTokenRevoker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $adminAuth,
        private readonly AccessTokenRevoker $tokenRevoker,
    ) {
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $session = $this->adminAuth->login($request->validated('email'), $request->validated('password'));

        return response()->json([
            'token' => $session->token,
            'admin' => new AdminResource($session->account),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->tokenRevoker->revokeCurrent($request->user());

        return response()->noContent();
    }

    public function me(Request $request): AdminResource
    {
        return new AdminResource($request->user());
    }
}
