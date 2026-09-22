<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Pages\PagePostReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagePostController extends Controller
{
    public function __construct(private readonly PagePostReader $posts)
    {
    }

    public function index(Request $request, string $pageId): JsonResponse
    {
        return response()->json(['data' => $this->posts->recent($request->user(), $pageId)]);
    }
}
