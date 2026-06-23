<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmbedAuth;

use App\Exceptions\EmbedAuth\InvalidTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmbedAuth\EmbedAuthRefreshRequest;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;

final class EmbedAuthRefreshController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokenService,
    ) {}

    public function __invoke(EmbedAuthRefreshRequest $request): JsonResponse
    {
        try {
            $tokens = $this->tokenService->refresh(
                plainRefreshToken: $request->string('refresh_token')->toString(),
            );
        } catch (InvalidTokenException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getHttpStatus());
        }

        return response()->json($tokens);
    }
}
