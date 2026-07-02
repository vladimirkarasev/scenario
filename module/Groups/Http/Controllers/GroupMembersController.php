<?php

declare(strict_types=1);

namespace Module\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Pagination;
use Module\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Groups\Http\Requests\GroupMemberStoreRequest;
use Module\Groups\Models\UserGroup;
use Module\Groups\Services\UserGroupService;
use Module\Users\Http\Resources\JsonApi\UserResource;

final class GroupMembersController extends Controller
{
    public function __construct(
        private readonly UserGroupService $service,
    ) {
    }

    public function index(Request $request, UserGroup $group): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->service->listMembers($group, Pagination::fromRequest($request)),
        );
    }

    public function store(GroupMemberStoreRequest $request, UserGroup $group): JsonResponse
    {
        $this->service->addMember($group, $request->integer('user_id'));

        return new JsonResponse(status: 204);
    }

    public function destroy(UserGroup $group, User $user): JsonResponse
    {
        $this->service->removeMember($group, $user);

        return new JsonResponse(status: 204);
    }
}
