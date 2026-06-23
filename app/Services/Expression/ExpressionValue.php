<?php

declare(strict_types=1);

namespace App\Services\Expression;

use ArrayAccess;
use JsonSerializable;
use LogicException;

/**
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ExpressionValue implements ArrayAccess, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        private array $values,
    ) {
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Expression context is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Expression context is read-only.');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
