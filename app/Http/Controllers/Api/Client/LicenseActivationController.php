<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ActivateLicenseKeyRequest;
use App\Http\Resources\Client\SubscriptionResource;
use App\Services\Licensing\LicenseActivationService;

class LicenseActivationController extends Controller
{
    public function __construct(private readonly LicenseActivationService $activation)
    {
    }

    public function store(ActivateLicenseKeyRequest $request): SubscriptionResource
    {
        return new SubscriptionResource($this->activation->activate($request->user(), $request->validated('key')));
    }
}
