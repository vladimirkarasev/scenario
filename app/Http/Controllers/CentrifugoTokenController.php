<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Users\Models\User;

final class CentrifugoTokenController extends Controller
{
    private const int TOKEN_TTL_SECONDS = 3600;

    public function connectionToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $secret = config('services.centrifugo.token_hmac_secret_key');
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('services.centrifugo.token_hmac_secret_key is not configured.');
        }

        $now = now();

        $token = JWT::encode([
            'sub' => (string) $user->id,
            'iat' => $now->timestamp,
            'exp' => $now->addSeconds(self::TOKEN_TTL_SECONDS)->timestamp,
        ], $secret, 'HS256');

        return response()->json([
            'token' => $token,
            'ws_url' => config('services.centrifugo.ws_url'),
        ]);
    }
}
