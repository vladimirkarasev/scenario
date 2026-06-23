<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Users\Http\Requests\TokenStoreRequest;
use Module\Users\Http\Resources\JsonApi\UserTokenResource;

final class UserTokenController extends Controller
{
    public function index(User $user): AnonymousResourceCollection
    {
        return UserTokenResource::collection(
            $user->tokens()->orderByDesc('created_at')->get(),
        );
    }

    public function store(TokenStoreRequest $request, User $user): JsonResponse
    {
        $token = $user->createToken($request->tokenName(), ['*'], $request->expiresAt());

        return new JsonResponse([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'last_used_at' => null,
                'created_at' => $token->accessToken->created_at?->toIso8601String(),
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'plain_text_token' => $token->plainTextToken,
            ],
        ], 201);
    }

    public function destroy(User $user, int $tokenId): JsonResponse
    {
        $user->tokens()->where('id', $tokenId)->delete();

        return new JsonResponse(status: 204);
    }
}
