<?php

declare(strict_types=1);

namespace Module\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Groups\DTO\UserGroupData;
use Module\Groups\DTO\UserGroupIndexData;
use Module\Groups\Http\Requests\UserGroupRequest;
use Module\Groups\Http\Resources\JsonApi\UserGroupResource;
use Module\Groups\Models\UserGroup;
use Module\Groups\Services\UserGroupService;

final class UserGroupsController extends Controller
{
    public function __construct(
        private readonly UserGroupService $service,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->service->paginate(UserGroupIndexData::fromRequest($request));

        return UserGroupResource::collection($paginator);
    }

    public function show(UserGroup $group): UserGroupResource
    {
        return new UserGroupResource($this->service->find($group));
    }

    public function store(UserGroupRequest $request): JsonResponse
    {
        $group = $this->service->create(
            UserGroupData::fromRequest($request),
            $request->user(),
        );

        return new JsonResponse(new UserGroupResource($group), 201);
    }

    public function update(UserGroupRequest $request, UserGroup $group): JsonResponse
    {
        $group = $this->service->update(
            UserGroupData::fromRequest($request),
            $group,
            $request->user(),
        );

        return new JsonResponse(new UserGroupResource($group));
    }

    public function destroy(UserGroup $group): JsonResponse
    {
        $this->service->delete($group);

        return new JsonResponse(status: 204);
    }
}
