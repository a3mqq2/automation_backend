<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Auth\FacebookAccountRevocationService;
use App\Services\Auth\FacebookDataDeletionService;
use App\Services\Meta\SignedRequestParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FacebookAccountController extends Controller
{
    public function __construct(
        private readonly SignedRequestParser $signedRequests,
        private readonly FacebookAccountRevocationService $revocations,
        private readonly FacebookDataDeletionService $deletions,
    ) {
    }

    public function deauthorize(Request $request): Response
    {
        $this->revocations->revoke($this->signedRequests->facebookUserId($request->input('signed_request')));

        return response()->noContent();
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $deletionRequest = $this->deletions->delete($this->signedRequests->facebookUserId($request->input('signed_request')));

        return response()->json([
            'url' => url('/api/facebook/data-deletion/status').'?code='.$deletionRequest->confirmation_code,
            'confirmation_code' => $deletionRequest->confirmation_code,
        ]);
    }

    public function deletionStatus(Request $request): JsonResponse
    {
        $deletionRequest = $this->deletions->findByConfirmationCode((string) $request->query('code'));

        if ($deletionRequest === null) {
            throw new ApiException(ErrorCode::ResourceNotFound);
        }

        return response()->json([
            'data' => [
                'confirmation_code' => $deletionRequest->confirmation_code,
                'status' => $deletionRequest->status->value,
                'completed_at' => $deletionRequest->completed_at?->toIso8601String(),
                'message' => __('data_deletion.'.$deletionRequest->status->value),
            ],
        ]);
    }
}
