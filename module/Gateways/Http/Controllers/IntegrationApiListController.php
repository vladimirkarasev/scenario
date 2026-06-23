<?php

declare(strict_types=1);

namespace Module\Gateways\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class IntegrationApiListController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'module' => 'gateways',
            'items' => [],
        ]);
    }
}
