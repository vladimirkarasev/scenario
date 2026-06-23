<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Proxy\Services\ProxyReceiverService;

final class PublicProxyController extends Controller
{
    public function __construct(private readonly ProxyReceiverService $receiver) {}

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $response = $this->receiver->receiveHttp($request, $uuid);

        return new JsonResponse($response->body, $response->statusCode, $response->headers);
    }
}
