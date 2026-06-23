<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmbedAuth;

use App\Http\Controllers\Controller;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmbedAuthLogoutController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokenService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $plainToken = $request->bearerToken();

        if ($plainToken !== null) {
            $this->tokenService->logout($plainToken);
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
