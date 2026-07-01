<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Users\DTO\RoleData;
use Module\Users\Http\Requests\RoleRequest;
use Module\Users\Http\Resources\JsonApi\RoleResource;
use Module\Users\Models\Role;
use Module\Users\Services\RoleService;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
            ? $filter['search']
            : null;

        return RoleResource::collection(
            $this->roleService->paginate(
                max(1, min(100, $request->integer('page.size', 20))),
                $search,
            ),
        );
    }

    public function store(RoleRequest $request): JsonResponse
    {
        /** @var \Module\Users\Models\User $actor */
        $actor = $request->user();

        return new JsonResponse(
            new RoleResource($this->roleService->create($actor, RoleData::fromRequest($request))),
            201,
        );
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        /** @var \Module\Users\Models\User $actor */
        $actor = $request->user();

        return new JsonResponse(
            new RoleResource($this->roleService->update($actor, RoleData::fromRequest($request), $role)),
        );
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        /** @var \Module\Users\Models\User $actor */
        $actor = $request->user();
        $this->roleService->delete($actor, $role);

        return new JsonResponse(status: 204);
    }
}
