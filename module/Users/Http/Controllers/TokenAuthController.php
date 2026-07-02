<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TokenAuthController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokens,
    ) {
    }

    public function logout(Request $request): JsonResponse
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            $this->tokens->logout($bearer);
        }

        return new ApiResponse(['message' => 'Logged out.']);
    }
}
