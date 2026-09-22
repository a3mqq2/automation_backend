<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\BotFlowListRequest;
use App\Http\Requests\Client\SimulateBotFlowRequest;
use App\Http\Requests\Client\StoreBotFlowRequest;
use App\Http\Requests\Client\UpdateBotFlowRequest;
use App\Http\Resources\Client\BotFlowDetailResource;
use App\Http\Resources\Client\BotFlowResource;
use App\Http\Resources\Client\BotFlowVersionResource;
use App\Services\Automation\BotFlowPublisher;
use App\Services\Automation\BotFlowService;
use App\Services\Automation\Engine\FlowSimulator;
use App\Support\BotFlows\FlowIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BotFlowController extends Controller
{
    public function __construct(
        private readonly BotFlowService $flows,
        private readonly BotFlowPublisher $publisher,
        private readonly FlowSimulator $simulator,
    ) {
    }

    public function index(BotFlowListRequest $request): AnonymousResourceCollection
    {
        return BotFlowResource::collection($this->flows->paginate($request->user(), $request->listQuery()));
    }

    public function store(StoreBotFlowRequest $request): BotFlowResource
    {
        return new BotFlowResource($this->flows->create($request->validated()));
    }

    public function show(Request $request, string $botFlow): BotFlowDetailResource
    {
        $flow = $this->flows->find($request->user(), $botFlow);

        return new BotFlowDetailResource($flow, $this->issues($flow));
    }

    public function update(UpdateBotFlowRequest $request, string $botFlow): BotFlowDetailResource
    {
        $flow = $this->flows->update($request->user(), $botFlow, $request->validated());

        return new BotFlowDetailResource($flow, $this->issues($flow));
    }

    public function destroy(Request $request, string $botFlow): Response
    {
        $this->flows->delete($request->user(), $botFlow);

        return response()->noContent();
    }

    public function publish(Request $request, string $botFlow): BotFlowDetailResource
    {
        $flow = $this->flows->find($request->user(), $botFlow);
        $this->publisher->publish($flow);

        $published = $this->flows->find($request->user(), $botFlow);

        return new BotFlowDetailResource($published, $this->issues($published));
    }

    public function versions(Request $request, string $botFlow): AnonymousResourceCollection
    {
        $flow = $this->flows->find($request->user(), $botFlow);

        return BotFlowVersionResource::collection($flow->versions()->with('botFlow')->get());
    }

    public function restoreVersion(Request $request, string $botFlow, string $version): BotFlowDetailResource
    {
        $flow = $this->flows->find($request->user(), $botFlow);
        $restored = $this->publisher->restore($flow, $flow->versions()->where('version', $version)->firstOrFail());

        return new BotFlowDetailResource($restored, $this->issues($restored));
    }

    public function simulate(SimulateBotFlowRequest $request, string $botFlow): JsonResponse
    {
        $flow = $this->flows->find($request->user(), $botFlow);

        return response()->json(['data' => $this->simulator->run($flow, $request->customerMessages())]);
    }

    private function issues($flow): array
    {
        return array_map(fn (FlowIssue $issue) => $issue->toArray(), $this->publisher->issues($flow));
    }
}
