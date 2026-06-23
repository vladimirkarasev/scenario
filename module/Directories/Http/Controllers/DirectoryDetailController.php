<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Module\Directories\Http\Resources\JsonApi\DirectoryResource;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;

final class DirectoryDetailController extends Controller
{
    public function __construct(
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function show(Request $request, Directory $directory): DirectoryResource
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        return new DirectoryResource($this->directoryService->find($directory));
    }
}
