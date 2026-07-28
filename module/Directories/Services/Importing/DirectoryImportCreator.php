<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Module\Directories\DTO\DirectoryImportPlan;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryImportRepository;

final readonly class DirectoryImportCreator
{
    public function __construct(
        private DirectoryImportRepository $imports,
        private DirectoryImportState $state,
    ) {
    }

    public function create(DirectoryImportPlan $plan): DirectoryImport
    {
        $import = $this->imports->create([
            'directory_id' => $plan->directory->id,
            'directory_version_id' => $plan->version->id,
            'uploaded_by' => $plan->uploadedBy,
            'mode' => $plan->mode->value,
            'status' => DirectoryImportStatus::Pending->value,
            'source_type' => $plan->source->value,
            'file_disk' => $plan->disk,
            'file_path' => $plan->path,
            'match_by' => $plan->matchBy,
            'external_key_field' => $plan->externalKeyField,
            'parent_key_field' => $plan->parentKeyField,
            'chunk_size' => $plan->chunkSize,
            'mapping_json' => $plan->mapping,
            'fields_json' => $plan->fields,
            'source_config_json' => [
                ...$plan->sourceConfig->toArray(),
                'activate_on_success' => $plan->activate,
                ...$plan->options->toArray(),
            ],
            'processed_keys_json' => [],
        ]);

        $plan->version->forceFill([
            'source_import_id' => $import->id,
            'source_metadata_json' => [
                'source' => $plan->source->value,
                'queued_at' => now()->toIso8601String(),
            ],
        ])->save();

        $this->state->publish($import);

        return $import;
    }
}
