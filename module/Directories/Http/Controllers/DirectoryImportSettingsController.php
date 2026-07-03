<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Directories\Http\Requests\UpdateDirectoryImportSettingsRequest;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;

final class DirectoryImportSettingsController extends Controller
{
    public function __construct(
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function update(UpdateDirectoryImportSettingsRequest $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $this->directoryService->updateImportSettings($directory, $validated);

        return response()->json(['ok' => true]);
    }
}
