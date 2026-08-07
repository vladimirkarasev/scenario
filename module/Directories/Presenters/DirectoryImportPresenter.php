<?php

declare(strict_types=1);

namespace Module\Directories\Presenters;

use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\DirectoryImport;

final class DirectoryImportPresenter
{
    /** @return array<string, mixed> */
    public function present(DirectoryImport $import): array
    {
        $import->loadMissing('version');

        return [
            'id' => $import->id,
            'directory_id' => $import->directory_id,
            'directory_version_id' => $import->directory_version_id,
            'version_number' => $import->version?->version_number,
            'mode' => $import->mode,
            'status' => $import->status,
            'source_type' => $import->source_type,
            'source_label' => DirectoryImportSourceType::from($import->source_type)->label(),
            'match_by' => $import->match_by,
            'external_key_field' => $import->external_key_field,
            'parent_key_field' => $import->parent_key_field,
            'chunk_size' => $import->chunk_size,
            'processed_rows' => $import->processed_rows,
            'imported_rows' => $import->imported_rows,
            'failed_rows' => $import->failed_rows,
            'error_message' => $import->error_message,
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
        ];
    }
}
