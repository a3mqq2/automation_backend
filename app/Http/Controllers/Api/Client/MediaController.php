<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeleteMediaRequest;
use App\Http\Requests\Client\StoreMediaRequest;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $media)
    {
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->media->store($request->user(), $request->file('file'))],
            201,
        );
    }

    public function destroy(DeleteMediaRequest $request): Response
    {
        $this->media->delete($request->user(), $request->validated('path'));

        return response()->noContent();
    }
}
