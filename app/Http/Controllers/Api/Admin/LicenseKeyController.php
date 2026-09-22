<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LicenseKeyListRequest;
use App\Http\Requests\Admin\StoreLicenseKeyRequest;
use App\Http\Resources\Admin\LicenseKeyResource;
use App\Models\LicenseKey;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LicenseKeyController extends Controller
{
    public function __construct(private readonly LicenseKeyService $licenseKeys)
    {
    }

    public function index(LicenseKeyListRequest $request): AnonymousResourceCollection
    {
        return LicenseKeyResource::collection($this->licenseKeys->paginate($request->listQuery()));
    }

    public function store(StoreLicenseKeyRequest $request): JsonResponse
    {
        $licenseKeys = $this->licenseKeys->issue(
            $request->user(),
            $request->expiresAt(),
            $request->note(),
            $request->quantity(),
        );

        return LicenseKeyResource::collection($licenseKeys)->response()->setStatusCode(201);
    }

    public function show(LicenseKey $licenseKey): LicenseKeyResource
    {
        return new LicenseKeyResource($this->licenseKeys->detail($licenseKey));
    }

    public function destroy(LicenseKey $licenseKey): Response
    {
        $this->licenseKeys->delete($licenseKey);

        return response()->noContent();
    }
}
