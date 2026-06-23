<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Module\Directories\DTO\DirectoryImportData;
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
        RemoteDirectoryImportSource $remoteSource,
        ProxyDirectoryImportSource $proxySource,
    ) {
        $this->sources = [
            $fileSource->type()->value => $fileSource,
            $remoteSource->type()->value => $remoteSource,
            $proxySource->type()->value => $proxySource,
        ];
    }

    public function forData(DirectoryImportData $data): DirectoryImportSource
    {
        return $this->resolve($data->sourceType);
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
