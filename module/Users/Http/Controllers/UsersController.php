<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Module\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Users\DTO\UserData;
use Module\Users\DTO\UserIndexData;
use Module\Users\Http\Requests\UserStoreRequest;
use Module\Users\Http\Requests\UserIndexRequest;
use Module\Users\Http\Requests\UserUpdateRequest;
use Module\Users\Http\Resources\JsonApi\UserResource;
use Module\Users\Services\UserService;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UserService $service,
    ) {
    }

    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->service->paginate(UserIndexData::fromRequest($request)),
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($this->service->find($user));
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $this->service->create($actor, UserData::fromRequest($request));

        return new JsonResponse(new UserResource($user), 201);
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $this->service->update($actor, UserData::fromRequest($request), $user);

        return new JsonResponse(new UserResource($user));
    }

    public function destroy(\Illuminate\Http\Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->delete($actor, $user);

        return new JsonResponse(status: 204);
    }
}
