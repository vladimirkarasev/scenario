<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ProxyPagination
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public ?int $from,
        public ?int $to,
    ) {}

    /** @param  LengthAwarePaginator<int, mixed>  $paginator */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            from: $paginator->firstItem(),
            to: $paginator->lastItem(),
        );
    }

    /** @param  array<array-key, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            currentPage: is_int($data['current_page'] ?? null) ? $data['current_page'] : 1,
            lastPage: is_int($data['last_page'] ?? null) ? $data['last_page'] : 1,
            perPage: is_int($data['per_page'] ?? null) ? $data['per_page'] : 0,
            total: is_int($data['total'] ?? null) ? $data['total'] : 0,
            from: is_int($data['from'] ?? null) ? $data['from'] : null,
            to: is_int($data['to'] ?? null) ? $data['to'] : null,
        );
    }

    /** @return array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null} */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
