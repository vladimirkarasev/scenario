<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use InvalidArgumentException;

final readonly class ExcelFilePlan
{
    /** @param list<string> $headers */
    public function __construct(
        public string $disk,
        public string $path,
        public int $firstDataRow,
        public int $totalRows,
        public array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $disk = $data['disk'] ?? null;
        $path = $data['path'] ?? null;

        if (!is_string($disk) || $disk === '' || !is_string($path) || $path === '') {
            throw new InvalidArgumentException('Invalid Excel file plan.');
        }

        return new self(
            disk: $disk,
            path: $path,
            firstDataRow: self::integer($data['first_data_row'] ?? null, 2),
            totalRows: self::integer($data['total_rows'] ?? null, 0),
            headers: self::strings($data['headers'] ?? null),
        );
    }

    public function chunks(int $chunkSize): int
    {
        $rows = max(0, $this->totalRows - $this->firstDataRow + 1);

        return (int)ceil($rows / max(1, $chunkSize));
    }

    /** @return array{disk: string, path: string, first_data_row: int, total_rows: int, headers: list<string>} */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'first_data_row' => $this->firstDataRow,
            'total_rows' => $this->totalRows,
            'headers' => $this->headers,
        ];
    }

    private static function integer(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, static fn(mixed $item): bool => is_string($item)))
            : [];
    }
}
