<?php

declare(strict_types=1);

namespace Module\Directories\Services\Handlers;

use Illuminate\Contracts\Container\Container;
use Module\Directories\Enums\DirectorySourceType;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Exceptions\DirectoryImportException;
use LogicException;

final class DirectoryHandlerResolver
{
    /** @var array<string, DirectoryHandler> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function resolve(DirectorySourceType $type): DirectoryHandler
    {
        if (isset($this->resolved[$type->value])) {
            return $this->resolved[$type->value];
        }

        foreach (DirectoryHandlerRegistry::handlers() as $handlerClass) {
            $handler = $this->container->make($handlerClass);

            if (!$handler instanceof DirectoryHandler) {
                throw new LogicException("Directory handler [{$handlerClass}] must implement DirectoryHandler.");
            }

            if ($handler->type() === $type) {
                return $this->resolved[$type->value] = $handler;
            }
        }

        throw DirectoryException::unsupportedType($type->value);
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
