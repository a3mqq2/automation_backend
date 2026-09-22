<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\AutomationRuleListRequest;
use App\Http\Requests\Client\StoreAutomationRuleRequest;
use App\Http\Requests\Client\UpdateAutomationRuleRequest;
use App\Http\Resources\Client\AutomationRuleResource;
use App\Services\Automation\AutomationRuleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AutomationRuleController extends Controller
{
    public function __construct(private readonly AutomationRuleService $rules)
    {
    }

    public function index(AutomationRuleListRequest $request): AnonymousResourceCollection
    {
        return AutomationRuleResource::collection($this->rules->paginate($request->user(), $request->listQuery()));
    }

    public function store(StoreAutomationRuleRequest $request): AutomationRuleResource
    {
        return new AutomationRuleResource($this->rules->create($request->validated()));
    }

    public function show(Request $request, string $rule): AutomationRuleResource
    {
        return new AutomationRuleResource($this->rules->find($request->user(), $rule));
    }

    public function update(UpdateAutomationRuleRequest $request, string $rule): AutomationRuleResource
    {
        return new AutomationRuleResource($this->rules->update($request->user(), $rule, $request->validated()));
    }

    public function destroy(Request $request, string $rule): Response
    {
        $this->rules->delete($request->user(), $rule);

        return response()->noContent();
    }
}
