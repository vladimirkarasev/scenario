<?php

declare(strict_types=1);

namespace Module\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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

    public function index(UserGroup $group): AnonymousResourceCollection
    {
        $members = $group->members()->orderBy('name')->paginate(50, ['*'], 'page[number]');

        return UserResource::collection($members);
    }

    public function store(GroupMemberStoreRequest $request, UserGroup $group): JsonResponse
    {
        /** @var User $user */
        $user = User::query()->findOrFail($request->integer('user_id'));

        $this->service->addMember($group, $user);

        return new JsonResponse(status: 204);
    }

    public function destroy(UserGroup $group, User $user): JsonResponse
    {
        $this->service->removeMember($group, $user);

        return new JsonResponse(status: 204);
    }
}
