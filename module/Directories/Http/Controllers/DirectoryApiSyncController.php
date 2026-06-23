<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Http\Resources\JsonApi\DirectoryImportResource;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DictionaryApiSyncService;
use Module\Directories\Services\DirectoryService;

final class DirectoryApiSyncController extends Controller
{
    public function __construct(
        private readonly DictionaryApiSyncService $syncService,
        private readonly DirectoryService $directoryService,
    ) {}

    public function store(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $authId = $request->user()?->getAuthIdentifier();

        $options = null;
        if ($request->hasAny(['add_new', 'update_existing', 'delete_unused'])) {
            $options = new DirectoryImportOptions(
                addNew: (bool) $request->input('add_new', true),
                updateExisting: (bool) $request->input('update_existing', true),
                deleteUnused: (bool) $request->input('delete_unused', true),
            );
        }

        $import = $this->syncService->queue($directory, is_int($authId) ? $authId : null, $options);

        return new DirectoryImportResource($this->directoryService->importPayload($import))
            ->response()
            ->setStatusCode(202);
    }
}
