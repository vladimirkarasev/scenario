<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;

final readonly class DirectoryImportPlan
{
    /**
     * @param array<string, string> $mapping
     * @param array<int, array<string, mixed>> $fields
     */
    public function __construct(
        public Directory $directory,
        public DirectoryVersion $version,
        public DirectoryImportSourceType $source,
        public DirectoryImportMode $mode,
        public string $disk,
        public string $path,
        public ImportSourceConfig $sourceConfig,
        public array $mapping,
        public array $fields,
        public DirectoryImportOptions $options,
        public ?string $matchBy,
        public ?string $externalKeyField,
        public ?string $parentKeyField,
        public int $chunkSize,
        public bool $activate,
        public ?int $uploadedBy,
    ) {
    }
}
