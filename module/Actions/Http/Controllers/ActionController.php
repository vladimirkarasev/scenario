<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Actions\DTO\ActionData;
use Module\Actions\DTO\ActionIndexData;
use Module\Actions\Http\Requests\ActionRequest;
use Module\Actions\Http\Resources\JsonApi\ActionResource;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionService;

final class ActionController extends Controller
{
    public function __construct(
        private readonly ActionService $actionService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ActionResource::collection($this->actionService->paginate(ActionIndexData::fromRequest($request)));
    }

    public function show(Action $action): ActionResource
    {
        return new ActionResource($this->actionService->find($action));
    }

    public function store(ActionRequest $request): JsonResponse
    {
        return new JsonResponse(
            new ActionResource($this->actionService->create(ActionData::fromRequest($request))),
            201,
        );
    }

    public function update(ActionRequest $request, Action $action): JsonResponse
    {
        return new JsonResponse(
            new ActionResource($this->actionService->update(ActionData::fromRequest($request, $action), $action)),
        );
    }

    public function destroy(Request $request, Action $action): JsonResponse
    {
        $this->actionService->delete(
            canManageActions: $request->user() !== null,
            action: $action,
        );

        return new JsonResponse(status: 204);
    }
}
