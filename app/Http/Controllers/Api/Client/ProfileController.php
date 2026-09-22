<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ProfileResource;
use App\Services\Auth\ClientProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ClientProfileService $profiles)
    {
    }

    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($this->profiles->profile($request->user()));
    }
}
