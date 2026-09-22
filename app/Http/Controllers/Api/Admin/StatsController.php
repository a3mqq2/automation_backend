<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformStatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(private readonly PlatformStatsService $stats)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->stats->summary()]);
    }
}
