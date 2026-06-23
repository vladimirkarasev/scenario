<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmbedAuth;

use App\Exceptions\EmbedAuth\InvalidTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmbedAuth\EmbedAuthExchangeRequest;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;

final class EmbedAuthExchangeController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokenService,
    ) {
    }

    public function __invoke(EmbedAuthExchangeRequest $request): JsonResponse
    {
        try {
            $tokens = $this->tokenService->exchange(
                plainToken: $request->string('token')->toString(),
                requestOrigin: $request->header('Origin'),
            );
        } catch (InvalidTokenException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getHttpStatus());
        }

        return response()->json($tokens);
    }
}
