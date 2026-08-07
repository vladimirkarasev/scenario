<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories\Services\Handlers;

use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Services\Handlers\DirectoryHandler;
use Module\Directories\Services\Handlers\DirectoryHandlerRegistry;
use Module\Directories\Services\Handlers\DirectoryHandlerResolver;
use Module\Directories\Services\Handlers\ImportHandler;
use Tests\TestCase;

final class DirectoryHandlerRegistryTest extends TestCase
{
    public function test_catalog_yields_every_directory_type(): void
    {
        $handlers = collect(DirectoryHandlerRegistry::handlers())
            ->map(fn(string $class): DirectoryHandler => $this->app->make($class))
            ->keyBy(static fn(DirectoryHandler $handler): string => $handler->type()->value);

        $this->assertSame(
            array_column(DirectorySourceType::cases(), 'value'),
            $handlers->keys()->all(),
        );
        $this->assertFalse($handlers[DirectorySourceType::Manual->value] instanceof ImportHandler);
        $this->assertTrue($handlers[DirectorySourceType::Excel->value] instanceof ImportHandler);
        $this->assertTrue($handlers[DirectorySourceType::Api->value] instanceof ImportHandler);
        $this->assertFalse($handlers[DirectorySourceType::External->value] instanceof ImportHandler);
    }

    public function test_resolver_rejects_import_for_type_without_import_capability(): void
    {
        $this->expectException(DirectoryImportException::class);
        $this->expectExceptionMessage('Directory type [manual] does not support import.');

        $this->app->make(DirectoryHandlerResolver::class)
            ->resolveImporter(DirectorySourceType::Manual);
    }
}
