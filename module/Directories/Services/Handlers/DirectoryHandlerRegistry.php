<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

final class DirectoryHandlerRegistry
{
    private function __construct()
    {
    }

    /** @return iterable<class-string<DirectoryHandler>> */
    public static function handlers(): iterable
    {
        yield ManualDirectoryHandler::class;
        yield ExcelDirectoryHandler::class;
        yield ApiDirectoryHandler::class;
        yield ExternalDirectoryHandler::class;
    }
}
