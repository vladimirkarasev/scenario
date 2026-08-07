<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

final readonly class ExcelImportSourceConfig implements ImportSourceConfig
{
    /** @param list<ExcelFilePlan> $files */
    public function __construct(
        public array $files,
        public int $totalChunks,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $files = [];
        $rawFiles = $data['files'] ?? [];

        if (is_array($rawFiles)) {
            foreach ($rawFiles as $file) {
                if (is_array($file)) {
                    $normalized = [];

                    foreach ($file as $key => $value) {
                        if (is_string($key)) {
                            $normalized[$key] = $value;
                        }
                    }

                    $files[] = ExcelFilePlan::fromArray($normalized);
                }
            }
        }

        return new self(
            files: $files,
            totalChunks: is_numeric($data['totalChunks'] ?? null) ? (int)$data['totalChunks'] : 0,
        );
    }

    /** @return array{files: list<array{disk: string, path: string, first_data_row: int, total_rows: int, headers: list<string>}>, totalChunks: int} */
    public function toArray(): array
    {
        return [
            'files' => array_map(
                static fn(ExcelFilePlan $file): array => $file->toArray(),
                $this->files,
            ),
            'totalChunks' => $this->totalChunks,
        ];
    }
}
