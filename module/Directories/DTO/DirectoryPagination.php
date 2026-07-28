<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

final readonly class DirectoryPagination
{
    public function __construct(
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            currentPage: self::integer($data['current_page'] ?? null, 1),
            perPage: self::integer($data['per_page'] ?? null, 50),
            total: self::integer($data['total'] ?? null, 0),
            lastPage: self::integer($data['last_page'] ?? null, 1),
        );
    }

    /** @return array{current_page: int, per_page: int, total: int, last_page: int} */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage,
        ];
    }

    private static function integer(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }
}
