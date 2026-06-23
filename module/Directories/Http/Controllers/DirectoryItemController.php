<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\DTO\DirectoryItemUpdateData;
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

        $rawFilter = (array)$request->input('filter', []);

        $versionId = isset($rawFilter['version_id']) && is_string($rawFilter['version_id'])
            ? $rawFilter['version_id']
            : null;

        $searchRaw = $rawFilter['search'] ?? $rawFilter['q'] ?? null;
        $search = is_string($searchRaw) && $searchRaw !== '' ? trim($searchRaw) : null;

        $withOther = filter_var($rawFilter['with_other'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $filters = [];
        foreach ($rawFilter as $key => $value) {
            if (in_array($key, ['version_id', 'q', 'search', 'with_other'], true)) {
                continue;
            }
            if (!is_string($key)) {
                continue;
            }
            if (is_string($value) && $value !== '') {
                $filters[$key] = $value;
            } elseif (is_array($value)) {
                $cleaned = array_values(
                    array_filter(
                        array_map(static fn(mixed $v): string => is_string($v) ? $v : '', $value),
                        static fn(string $v): bool => $v !== ''
                    )
                );
                if ($cleaned !== []) {
                    $filters[$key] = $cleaned;
                }
            }
        }

        $filtersTo = [];
        foreach ((array)$request->input('filter_to', []) as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $filtersTo[$key] = $value;
            }
        }

        $sortKey = null;
        $sortDir = 'asc';
        $rawSort = $request->input('sort');
        if (!is_string($rawSort) || $rawSort === '') {
            $rawSort = is_string($directory->default_sort) ? $directory->default_sort : '';
        }
        if ($rawSort !== '') {
            if (str_starts_with($rawSort, '-')) {
                $sortKey = substr($rawSort, 1);
                $sortDir = 'desc';
            } else {
                $sortKey = $rawSort;
            }
        }

        return DirectoryItemResource::collection(
            $this->directoryItemService->items(
                $directory,
                $versionId,
                $filters,
                $filtersTo,
                $search,
                $sortKey,
                $sortDir,
                $withOther
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
