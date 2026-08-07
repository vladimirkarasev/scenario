<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\Http\Requests\StoreDirectoryVersionRequest;
use Module\Directories\Http\Requests\UpdateDirectoryVersionCodeRequest;
use Module\Directories\Http\Requests\UpdateDirectoryVersionSettingsRequest;
use Module\Directories\Http\Resources\JsonApi\DirectoryVersionResource;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryService;
use Module\Directories\Services\DirectoryVersionService;

final class DirectoryVersionController extends Controller
{
    public function __construct(
        private readonly DirectoryVersionService $versionService,
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function index(Request $request, Directory $directory): AnonymousResourceCollection
    {
        $this->ensureProjectAccess($request, $directory);

        return DirectoryVersionResource::collection($this->versionService->list($directory));
    }

    public function store(StoreDirectoryVersionRequest $request, Directory $directory): JsonResponse
    {
        $this->ensureProjectAccess($request, $directory);

        $version = $this->versionService->create(
            directory: $directory,
            clone: $request->boolean('clone'),
        );

        return (new DirectoryVersionResource($version))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Directory $directory, DirectoryVersion $version): JsonResponse
    {
        $this->ensureProjectAccess($request, $directory);

        $this->versionService->delete(
            directory: $directory,
            version: $version,
        );

        return new JsonResponse(null, 204);
    }

    public function updateSchema(
        Request $request,
        Directory $directory,
        DirectoryVersion $version
    ): DirectoryVersionResource {
        $this->ensureProjectAccess($request, $directory);

        $fields = collect($request->array('schema'))
            ->map(static function (mixed $field): array {
                $f = is_array($field) ? $field : [];
                $type = is_string($f['type'] ?? null) ? $f['type'] : 'string';

                return [
                    'key' => is_string($f['key'] ?? null) ? $f['key'] : '',
                    'name' => is_string($f['name'] ?? null) ? $f['name'] : '',
                    'type' => $type,
                    'filter_type' => is_string($f['filter_type'] ?? null) ? $f['filter_type'] : $type,
                    'filter_multiple' => (bool)($f['filter_multiple'] ?? false),
                    'nullable' => (bool)($f['nullable'] ?? true),
                    'filterable' => (bool)($f['filterable'] ?? false),
                    'searchable' => (bool)($f['searchable'] ?? false),
                    'filter_operator' => is_string($f['filter_operator'] ?? null) ? $f['filter_operator'] : 'contains',
                    'filter_placeholder' => is_string(
                        $f['filter_placeholder'] ?? null
                    ) ? $f['filter_placeholder'] : null,
                    'default' => $f['default'] ?? null,
                    'sort_order' => is_int($f['sort_order'] ?? null) ? $f['sort_order'] : 0,
                    'rules' => is_array($f['rules'] ?? null) ? $f['rules'] : [],
                    'options' => is_array($f['options'] ?? null) ? array_values(
                        array_filter(
                            array_map(static fn(mixed $v): string => is_scalar($v) ? (string)$v : '', $f['options']),
                            static fn(string $v): bool => $v !== ''
                        )
                    ) : [],
                    'related_directory_id' => is_string(
                        $f['related_directory_id'] ?? null
                    ) && $f['related_directory_id'] !== '' ? $f['related_directory_id'] : null,
                    'related_match_key' => is_string(
                        $f['related_match_key'] ?? null
                    ) && $f['related_match_key'] !== '' ? $f['related_match_key'] : null,
                    'related_template' => is_string(
                        $f['related_template'] ?? null
                    ) && $f['related_template'] !== '' ? $f['related_template'] : null,
                ];
            })
            ->values()
            ->all();

        $matchBy = $request->filled('match_by')
            ? $request->str('match_by')->toString()
            : null;

        $defaultSort = $request->filled('default_sort')
            ? $request->str('default_sort')->toString()
            : null;

        return new DirectoryVersionResource(
            $this->versionService->updateSchema($directory, $version, $fields, $matchBy, $defaultSort),
        );
    }

    public function updateSettings(
        UpdateDirectoryVersionSettingsRequest $request,
        Directory $directory,
        DirectoryVersion $version
    ): DirectoryVersionResource {
        $this->ensureProjectAccess($request, $directory);

        $syncOptions = null;
        if ($request->has('sync_options')) {
            $raw = $request->array('sync_options');
            $syncOptions = [
                'add_new' => (bool)($raw['add_new'] ?? true),
                'update_existing' => (bool)($raw['update_existing'] ?? true),
                'delete_unused' => (bool)($raw['delete_unused'] ?? false),
            ];
        }

        return new DirectoryVersionResource(
            $this->versionService->updateSettings(
                directory: $directory,
                version: $version,
                sourceType: $request->str('source_type')->toString(),
                syncOptions: $syncOptions,
                allowOther: $request->has('allow_other') ? $request->boolean('allow_other') : null,
                otherLabel: $request->has('other_label') ? $request->str('other_label')->toString() : null,
                otherExternalKey: $request->has('other_external_key') ? $request->str('other_external_key')->toString() : null,
            ),
        );
    }

    public function updateCode(
        UpdateDirectoryVersionCodeRequest $request,
        Directory $directory,
        DirectoryVersion $version
    ): DirectoryVersionResource {
        $this->ensureProjectAccess($request, $directory);

        return new DirectoryVersionResource(
            $this->versionService->updateCode(
                directory: $directory,
                version: $version,
                code: $request->filled('code') ? $request->str('code')->toString() : null,
            ),
        );
    }

    public function activate(
        Request $request,
        Directory $directory,
        DirectoryVersion $version
    ): DirectoryVersionResource {
        $this->ensureProjectAccess($request, $directory);

        return new DirectoryVersionResource(
            $this->versionService->activate(
                directory: $directory,
                version: $version,
            ),
        );
    }

    private function ensureProjectAccess(Request $request, Directory $directory): void
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );
    }
}
