<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Exceptions\DirectoryImportException;

final readonly class DirectoryHandlerResolver
{
    /** @var array<string, DirectoryHandler> */
    private array $resolved;

    public function __construct(
        ManualDirectoryHandler $manual,
        ExcelDirectoryHandler $excel,
        ApiDirectoryHandler $api,
        ExternalDirectoryHandler $external,
    ) {
        $this->resolved = [
            $manual->type()->value => $manual,
            $excel->type()->value => $excel,
            $api->type()->value => $api,
            $external->type()->value => $external,
        ];
    }

    public function resolve(DirectorySourceType $type): DirectoryHandler
    {
        return $this->resolved[$type->value] ?? throw DirectoryException::unsupportedType($type->value);
    }

    public function resolveImporter(DirectorySourceType $type): DirectoryHandler&ImportHandler
    {
        $handler = $this->resolve($type);

        if (!$handler instanceof ImportHandler) {
            throw new DirectoryImportException("Directory type [{$type->value}] does not support import.");
        }

        return $handler;
    }
}
