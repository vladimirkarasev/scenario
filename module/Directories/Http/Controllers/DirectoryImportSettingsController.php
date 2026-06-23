<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;

final class DirectoryImportSettingsController extends Controller
{
    public function __construct(
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function update(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:create,update,replace'],
            'chunk_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'match_by' => ['nullable', 'string', 'max:255'],
            'fields_text' => ['nullable', 'string'],
            'mapping_text' => ['nullable', 'string'],
            'add_new' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'delete_unused' => ['nullable', 'boolean'],
        ]);

        $existing = is_array($directory->import_settings_json) ? $directory->import_settings_json : [];
        $directory->forceFill([
            'import_settings_json' => array_merge(
                $existing,
                array_filter($validated, static fn(mixed $v): bool => $v !== null)
            ),
        ])->save();

        DirectoryCache::forgetDirectory($directory->id);

        return response()->json(['ok' => true]);
    }
}
