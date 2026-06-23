<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryCacheService;
use Module\Directories\Services\DirectoryService;

final class DirectoryCacheController extends Controller
{
    public function __construct(
        private readonly DirectoryCacheService $cacheService,
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function warmup(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        return new JsonResponse(
            ['meta' => ['warmed_rows' => $this->cacheService->warmup($directory)]],
            200,
            ['Content-Type' => 'application/vnd.api+json'],
        );
    }
}
