<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentrifugoTokenController extends Controller
{
    public function __construct(private readonly Centrifugo $centrifugo) {}

    public function connectionToken(Request $request): JsonResponse
    {
        $id = $request->user()?->getAuthIdentifier();
        $userId = is_string($id) || is_int($id) ? (string) $id : '';

        return response()->json([
            'token' => $this->centrifugo->generateConnectionToken($userId, 3600),
            'ws_url' => config('broadcasting.connections.centrifugo.ws_url'),
        ]);
    }

    public function subscribeToken(Request $request): JsonResponse
    {
        $id = $request->user()?->getAuthIdentifier();
        $userId = is_string($id) || is_int($id) ? (string) $id : '';
        $channel = (string) $request->query('channel', '');

        return response()->json([
            'token' => $this->centrifugo->generatePrivateChannelToken($userId, $channel, 3600),
        ]);
    }
}
