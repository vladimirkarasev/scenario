<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\DTO\DirectoryItemUpdateData;
use Module\Directories\DTO\DirectoryItemQuery;
use Module\Directories\Http\Requests\UpdateDirectoryItemRequest;
use Module\Directories\Http\Resources\JsonApi\DirectoryItemResource;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Services\DirectoryItemService;
use Module\Directories\Services\DirectoryService;

final class DirectoryItemController extends Controller
{
    public function __construct(
        private readonly DirectoryItemService $directoryItemService,
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function index(Request $request, Directory $directory): AnonymousResourceCollection
    {
        $this->ensureProjectAccess($request, $directory);

        return DirectoryItemResource::collection(
            $this->directoryItemService->items(
                $directory,
                DirectoryItemQuery::fromRequest($request, $directory),
            ),
        );
    }

    public function update(
        UpdateDirectoryItemRequest $request,
        Directory $directory,
        DirectoryItem $item
    ): DirectoryItemResource {
        $this->ensureProjectAccess($request, $directory);

        return new DirectoryItemResource(
            $this->directoryItemService->update(
                directory: $directory,
                item: $item,
                data: DirectoryItemUpdateData::fromRequest($request),
            ),
        );
    }

    public function destroy(Request $request, Directory $directory, DirectoryItem $item): JsonResponse
    {
        $this->ensureProjectAccess($request, $directory);

        $this->directoryItemService->delete(
            directory: $directory,
            item: $item,
        );

        return new JsonResponse(status: 204);
    }

    public function bulkDestroy(Request $request, Directory $directory): JsonResponse
    {
        $this->ensureProjectAccess($request, $directory);

        $ids = [];
        foreach ((array)$request->input('ids', []) as $v) {
            if (is_int($v) && $v > 0) {
                $ids[] = $v;
            } elseif (is_string($v) && ctype_digit($v) && (int)$v > 0) {
                $ids[] = (int)$v;
            }
        }

        if ($ids !== []) {
            $this->directoryItemService->bulkDelete($directory, $ids);
        }

        return new JsonResponse(status: 204);
    }

    private function ensureProjectAccess(Request $request, Directory $directory): void
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );
    }
}
