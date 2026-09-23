<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClientLoginRequest;
use App\Http\Requests\Auth\RegisterClientRequest;
use App\Http\Resources\Client\ProfileResource;
use App\Services\Auth\ClientPasswordLoginService;
use App\Services\Auth\ClientProfileService;
use App\Services\Auth\ClientRegistrationService;
use App\Support\AuthenticatedSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClientAuthController extends Controller
{
    public function __construct(
        private readonly ClientRegistrationService $registration,
        private readonly ClientPasswordLoginService $passwordLogin,
        private readonly ClientProfileService $profiles,
    ) {
    }

    public function register(RegisterClientRequest $request): JsonResponse
    {
        $session = $this->registration->register(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->sessionResponse($session, Response::HTTP_CREATED);
    }

    public function login(ClientLoginRequest $request): JsonResponse
    {
        $session = $this->passwordLogin->login($request->validated('email'), $request->validated('password'));

        return $this->sessionResponse($session);
    }

    private function sessionResponse(AuthenticatedSession $session, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $session->token,
            'user' => new ProfileResource($this->profiles->profile($session->account)),
        ], $status);
    }
}
