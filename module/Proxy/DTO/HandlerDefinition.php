<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class HandlerDefinition
{
    private string $label;

    private function __construct(
        public readonly string $class,
    ) {
        $this->label = $class;
    }

    public static function make(string $class): self
    {
        return new self($class);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
