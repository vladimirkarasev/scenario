<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\UploadedFile;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Http\Requests\StoreDirectoryImportRequest;
use Module\Directories\Models\Directory;

final readonly class DirectoryImportData
{
    /**
     * @param  array<string, string>  $mapping
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $remote
     */
    public function __construct(
        public Directory $directory,
        public ?UploadedFile $file,
        public DirectoryImportMode $mode,
        public DirectoryImportSourceType $sourceType,
        public array $mapping,
        public array $fields,
        public array $remote,
        public ?string $matchBy,
        public ?string $parentKeyField,
        public int $chunkSize,
        public bool $activate,
        public DirectoryImportOptions $options,
        public ?int $uploadedBy,
        public ?int $versionId,
    ) {
    }

    public static function fromRequest(StoreDirectoryImportRequest $request, Directory $directory): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $mode = is_string($validated['mode'] ?? null) ? $validated['mode'] : '';
        $sourceType = is_string($validated['source_type'] ?? null) ? $validated['source_type'] : '';
        /** @var array<string, string> $mapping */
        $mapping = is_array($validated['mapping'] ?? null) ? $validated['mapping'] : [];
        /** @var array<int, array<string, mixed>> $fields */
        $fields = is_array($validated['columns'] ?? null) ? $validated['columns'] : [];
        /** @var array<string, mixed> $remote */
        $remote = is_array($validated['remote'] ?? null) ? $validated['remote'] : [];
        $matchBy = is_string($validated['match_by'] ?? null) ? $validated['match_by'] : $directory->match_by;
        $parentKeyField = is_string($validated['parent_key_field'] ?? null) ? $validated['parent_key_field'] : null;
        $configDefault = config('import.default_chunk_size', 500);
        $chunkSize = is_int($validated['chunk_size'] ?? null) ? $validated['chunk_size'] : (is_int(
            $configDefault
        ) ? $configDefault : 500);
        $rawVersionId = $validated['version_id'] ?? null;
        $versionId = is_int($rawVersionId) ? $rawVersionId : (is_numeric($rawVersionId) ? (int)$rawVersionId : null);
        $options = DirectoryImportOptions::fromInput($validated, $mode);

        return new self(
            directory: $directory,
            file: $request->file('file'),
            mode: DirectoryImportMode::from($mode),
            sourceType: DirectoryImportSourceType::from($sourceType),
            mapping: $mapping,
            fields: $fields,
            remote: $remote,
            matchBy: $matchBy,
            parentKeyField: $parentKeyField,
            chunkSize: $chunkSize,
            activate: (bool)($validated['activate'] ?? true),
            options: $options,
            uploadedBy: ($id = $request->user()?->getAuthIdentifier()) !== null && is_int($id) ? $id : null,
            versionId: $versionId,
        );
    }
}
