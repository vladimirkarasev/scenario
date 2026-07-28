<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;

final class DirectoryImportSourceResolver
{
    /**
     * @var array<string, DirectoryImportSource>
     */
    private array $sources;

    public function __construct(
        FileDirectoryImportSource $fileSource,
        ProxyDirectoryImportSource $proxySource,
    ) {
        $this->sources = [
            $fileSource->type()->value => $fileSource,
            $proxySource->type()->value => $proxySource,
        ];
    }

    public function forImport(DirectoryImport $import): DirectoryImportSource
    {
        return $this->resolve(DirectoryImportSourceType::from($import->source_type));
    }

    private function resolve(DirectoryImportSourceType $type): DirectoryImportSource
    {
        return $this->sources[$type->value]
            ?? throw new DirectoryImportException("Unsupported import source [{$type->value}].");
    }
}
