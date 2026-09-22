<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\AvailablePageResource;
use App\Services\Pages\PageConnectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageController extends Controller
{
    public function __construct(private readonly PageConnectionService $pages)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AvailablePageResource::collection($this->pages->available($request->user()));
    }
}
