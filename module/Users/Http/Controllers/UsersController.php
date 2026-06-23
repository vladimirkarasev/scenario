<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Users\DTO\UserData;
use Module\Users\DTO\UserIndexData;
use Module\Users\Http\Requests\UserStoreRequest;
use Module\Users\Http\Requests\UserUpdateRequest;
use Module\Users\Http\Resources\JsonApi\UserResource;
use Module\Users\Services\UserService;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UserService $service,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
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
        $user = $this->service->create(UserData::fromRequest($request));

        return new JsonResponse(new UserResource($user), 201);
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $user = $this->service->update(UserData::fromRequest($request), $user);

        return new JsonResponse(new UserResource($user));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->service->delete($user);

        return new JsonResponse(status: 204);
    }
}
