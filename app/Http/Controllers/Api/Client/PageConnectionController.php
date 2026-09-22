<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ConnectedPageResource;
use App\Services\Pages\PageConnectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PageConnectionController extends Controller
{
    public function __construct(private readonly PageConnectionService $pages)
    {
    }

    public function store(Request $request, string $pageId): ConnectedPageResource
    {
        return new ConnectedPageResource($this->pages->connect($request->user(), $pageId));
    }

    public function destroy(Request $request, string $pageId): Response
    {
        $this->pages->disconnect($request->user(), $pageId);

        return response()->noContent();
    }
}
