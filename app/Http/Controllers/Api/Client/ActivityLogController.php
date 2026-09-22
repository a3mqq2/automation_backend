<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ActivityLogListRequest;
use App\Http\Resources\Activity\ActivityLogResource;
use App\Services\Activity\ActivityLogQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogQueryService $activityLogs)
    {
    }

    public function index(ActivityLogListRequest $request): AnonymousResourceCollection
    {
        return ActivityLogResource::collection($this->activityLogs->paginateForClient($request->user(), $request->listQuery()));
    }

    public function show(Request $request, string $activityLog): ActivityLogResource
    {
        return new ActivityLogResource($this->activityLogs->findForClient($request->user(), $activityLog));
    }
}
