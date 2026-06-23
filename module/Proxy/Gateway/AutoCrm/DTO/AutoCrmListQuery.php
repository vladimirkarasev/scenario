<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\DTO;

final readonly class AutoCrmListQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public array $filters = [],
        public int $firstPage = 1,
    ) {
    }

    /** @param  array<string, mixed>  $filters */
    public static function make(array $filters = [], int $firstPage = 1): self
    {
        return new self($filters, $firstPage);
    }

    /** @return array<string, mixed> */
    public function forPage(int $page): array
    {
        return array_filter([
            ...$this->filters,
            'page' => $page,
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }
}
