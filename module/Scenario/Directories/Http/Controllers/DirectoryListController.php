<?php

declare(strict_types=1);

namespace Module\Scenario\Directories\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class DirectoryListController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'section' => 'directories',
            'items' => [],
        ]);
    }
}
