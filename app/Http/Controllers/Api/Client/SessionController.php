<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccessTokenRevoker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    public function __construct(private readonly AccessTokenRevoker $tokenRevoker)
    {
    }

    public function destroy(Request $request): Response
    {
        $this->tokenRevoker->revokeCurrent($request->user());

        return response()->noContent();
    }
}
