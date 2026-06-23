<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\Roles\RoleActionData;
use App\DTO\Roles\RoleData;
use App\Http\Requests\Roles\RoleRequest;
use App\Http\Resources\JsonApi\RoleResource;
use App\Models\Role;
use App\Services\Roles\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
        return new JsonResponse(
            new RoleResource($this->roleService->create(RoleData::fromRequest($request))),
            201,
        );
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        return new JsonResponse(
            new RoleResource($this->roleService->update(RoleData::fromRequest($request, $role), $role)),
        );
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->roleService->delete(RoleActionData::fromRequest($request), $role);

        return new JsonResponse(status: 204);
    }
}
