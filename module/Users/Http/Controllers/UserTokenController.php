<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Module\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Users\Http\Requests\TokenStoreRequest;
use Module\Users\Http\Resources\JsonApi\UserTokenCreatedResource;
use Module\Users\Http\Resources\JsonApi\UserTokenResource;
use Module\Users\Services\UserTokenService;

final class UserTokenController extends Controller
{
    public function __construct(private readonly UserTokenService $service)
    {
    }

    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        return UserTokenResource::collection($this->service->list($actor, $user));
    }

    public function store(TokenStoreRequest $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $token = $this->service->create(
            $actor,
            $user,
            $request->tokenName(),
            $request->expiresAt(),
        );

        return (new UserTokenCreatedResource($token))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, User $user, int $tokenId): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->revoke($actor, $user, $tokenId);

        return new JsonResponse(status: 204);
    }
}
