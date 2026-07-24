<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class HandlerGroupDefinition
{
    /** @var list<HandlerDefinition> */
    private array $handlers = [];

    private function __construct(
        public readonly string $name,
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** @param list<HandlerDefinition> $handlers */
    public function handlers(array $handlers): self
    {
        $this->handlers = $handlers;

        return $this;
    }

    /** @return list<HandlerDefinition> */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
