<?php

declare(strict_types=1);

namespace Module\Directories\Presenters;

use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryVersionRepository;

final readonly class DirectoryVersionPresenter
{
    public function __construct(private DirectoryVersionRepository $versions)
    {
    }

    /** @return array<string, mixed> */
    public function present(DirectoryVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'code' => $version->code,
            'status' => $version->status,
            'is_active' => (bool)$version->is_active,
            'source_type' => $version->source_type ?? 'manual',
            'sync_options' => $version->sync_options ?? [
                'add_new' => true,
                'update_existing' => true,
                'delete_unused' => false,
            ],
            'allow_other' => (bool)$version->allow_other,
            'other_label' => $version->other_label,
            'other_external_key' => $version->other_external_key,
            'schema_json' => $this->schema($version),
            'items_count' => $version->items_count ?? $this->versions->itemsCount($version),
            'imports_count' => $version->imports_count ?? $this->versions->importsCount($version),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function schema(DirectoryVersion $version): array
    {
        if ($version->schema_json !== []) {
            return $version->schema_json;
        }

        $import = $version->sourceImport()->first() ?? $version->imports()->latest()->first();

        return $import->fields_json ?? [];
    }
}
