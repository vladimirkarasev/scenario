<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Http\Requests\StoreDirectoryImportRequest;
use Module\Directories\Models\Directory;

final readonly class ExcelImportData
{
    /**
     * @param array<string, string> $mapping
     * @param array<int, array<string, mixed>> $fields
     */
    public function __construct(
        public Directory $directory,
        public ExcelImportFiles $files,
        public DirectoryImportMode $mode,
        public array $mapping,
        public array $fields,
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
        /** @var array<string, string> $mapping */
        $mapping = is_array($validated['mapping'] ?? null) ? $validated['mapping'] : [];
        /** @var array<int, array<string, mixed>> $fields */
        $fields = is_array($validated['columns'] ?? null) ? $validated['columns'] : [];
        $matchBy = is_string($validated['match_by'] ?? null) ? $validated['match_by'] : $directory->match_by;
        $parentKeyField = is_string($validated['parent_key_field'] ?? null) ? $validated['parent_key_field'] : null;
        $configDefault = config('import.default_chunk_size', 500);
        $chunkSize = is_int($validated['chunk_size'] ?? null)
            ? $validated['chunk_size']
            : (is_int($configDefault) ? $configDefault : 500);
        $rawVersionId = $validated['version_id'] ?? null;
        $versionId = is_int($rawVersionId) ? $rawVersionId : (is_numeric($rawVersionId) ? (int)$rawVersionId : null);

        return new self(
            directory: $directory,
            files: new UploadedExcelFiles(self::uploadedFiles($request)),
            mode: DirectoryImportMode::from($mode),
            mapping: $mapping,
            fields: $fields,
            matchBy: $matchBy,
            parentKeyField: $parentKeyField,
            chunkSize: $chunkSize,
            activate: (bool)($validated['activate'] ?? true),
            options: DirectoryImportOptions::fromInput($validated, $mode),
            uploadedBy: ($id = $request->user()?->getAuthIdentifier()) !== null && is_int($id) ? $id : null,
            versionId: $versionId,
        );
    }

    /** @return non-empty-list<UploadedFile> */
    private static function uploadedFiles(StoreDirectoryImportRequest $request): array
    {
        $value = $request->file('files');
        $files = [];

        if (is_array($value)) {
            foreach ($value as $file) {
                $files[] = $file;
            }
        }

        $single = $request->file('file');
        if ($files === [] && $single !== null) {
            $files[] = $single;
        }

        if ($files === []) {
            throw new InvalidArgumentException('At least one Excel file is required.');
        }

        /** @var non-empty-list<UploadedFile> $files */
        return $files;
    }
}
