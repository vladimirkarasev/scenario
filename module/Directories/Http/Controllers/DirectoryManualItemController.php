<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Directories\DTO\DirectoryManualItemData;
use Module\Directories\Http\Requests\StoreDirectoryManualItemRequest;
use Module\Directories\Http\Resources\JsonApi\DirectoryItemResource;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryManualItemService;
use Module\Directories\Services\DirectoryService;

final class DirectoryManualItemController extends Controller
{
    public function __construct(
        private readonly DirectoryManualItemService $directoryManualItemService,
        private readonly DirectoryService $directoryService,
    ) {}

    public function store(StoreDirectoryManualItemRequest $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        return (new DirectoryItemResource(
            $this->directoryManualItemService->create(
                $directory,
                DirectoryManualItemData::fromRequest($request),
            ),
        ))->response()->setStatusCode(201);
    }
}
