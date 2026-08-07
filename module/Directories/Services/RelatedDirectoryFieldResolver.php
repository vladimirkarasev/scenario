<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Module\Directories\Models\Directory;
use Module\Directories\Repositories\DirectoryCacheRepository;

final readonly class RelatedDirectoryFieldResolver
{
    public function __construct(private DirectoryCacheRepository $cache)
    {
    }

    /**
     * Резолвит поля типа `related_directory`: для каждой строки источника находит запись
     * цели по значению этого же поля и рендерит шаблон. Только 1 уровень — related-поля
     * найденной записи цели не разворачиваются рекурсивно.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function resolve(array $rows, array $schema): array
    {
        /** @var array<int, array<string, array<string, mixed>>> $result */
        $result = array_fill(0, count($rows), []);

        if ($rows === []) {
            return $result;
        }

        foreach ($schema as $field) {
            if (($field['type'] ?? null) !== 'related_directory') {
                continue;
            }

            $this->resolveField($rows, $field, $result);
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $field
     * @param  array<int, array<string, array<string, mixed>>>  $result
     */
    private function resolveField(array $rows, array $field, array &$result): void
    {
        $key = is_string($field['key'] ?? null) ? $field['key'] : null;
        $directoryId = is_string($field['related_directory_id'] ?? null) ? $field['related_directory_id'] : null;
        $matchKey = is_string($field['related_match_key'] ?? null) ? $field['related_match_key'] : null;
        $template = is_string($field['related_template'] ?? null) ? $field['related_template'] : null;

        if ($key === null || $directoryId === null || $matchKey === null || $template === null || $template === '') {
            return;
        }

        $targetDirectory = Directory::query()->find($directoryId);

        if (!$targetDirectory instanceof Directory) {
            return;
        }

        $lookup = $this->buildLookup($this->cache->activeRows($targetDirectory), $matchKey);

        if ($lookup === []) {
            return;
        }

        foreach ($rows as $index => $row) {
            $localValue = $row[$key] ?? null;

            if ($localValue === null || $localValue === '' || !is_scalar($localValue)) {
                continue;
            }

            $targetRow = $lookup[(string)$localValue] ?? null;

            if ($targetRow === null) {
                continue;
            }

            $context = [...$row, ...$targetRow];
            $result[$index][$key] = [...$targetRow, 'label' => $this->renderTemplate($template, $context)];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $targetRows
     * @return array<string, array<string, mixed>>
     */
    private function buildLookup(array $targetRows, string $matchKey): array
    {
        $lookup = [];

        foreach ($targetRows as $row) {
            $value = $row[$matchKey] ?? null;

            if ($value === null || $value === '' || !is_scalar($value)) {
                continue;
            }

            $lookup[(string)$value] = $row;
        }

        return $lookup;
    }

    /** @param  array<string, mixed>  $context */
    private function renderTemplate(string $template, array $context): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            static function (array $matches) use ($context): string {
                $value = $context[$matches[1]] ?? null;

                return is_scalar($value) ? (string)$value : '';
            },
            $template,
        );

        return $rendered ?? '';
    }
}
