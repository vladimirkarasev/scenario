<?php

declare(strict_types=1);

namespace App\DTO\Expression;

final readonly class ExpressionContextData
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public array $values,
    ) {
    }

    public static function from(mixed $context): self
    {
        if (! is_array($context)) {
            return new self([]);
        }

        $values = [];
        foreach ($context as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return new self($values);
    }
}
