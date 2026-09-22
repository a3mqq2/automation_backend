<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminActivityLogListRequest;
use App\Http\Resources\Activity\ActivityLogResource;
use App\Models\ActivityLog;
use App\Services\Activity\ActivityLogQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogQueryService $activityLogs)
    {
    }

    public function index(AdminActivityLogListRequest $request): AnonymousResourceCollection
    {
        return ActivityLogResource::collection($this->activityLogs->paginateForAdmin($request->listQuery()));
    }

    public function show(ActivityLog $activityLog): ActivityLogResource
    {
        return new ActivityLogResource($this->activityLogs->detailForAdmin($activityLog));
    }
}
