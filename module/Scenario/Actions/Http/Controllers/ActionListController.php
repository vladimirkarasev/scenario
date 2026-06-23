<?php

declare(strict_types=1);

namespace Module\Scenario\Actions\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ActionListController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'section' => 'actions',
            'items' => [],
        ]);
    }
}
