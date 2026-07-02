<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Пагинация JSON:API (`page[number]` / `page[size]`) с дефолтами и клампингом.
 * Единый источник значений по умолчанию для всех списочных DTO.
 */
final readonly class Pagination
{
    public const int DEFAULT_SIZE = 20;
    public const int MAX_SIZE = 100;

    public function __construct(
        public int $number,
        public int $size,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            number: max(1, $request->integer('page.number', 1)),
            size: max(1, min(self::MAX_SIZE, $request->integer('page.size', self::DEFAULT_SIZE))),
        );
    }
}
