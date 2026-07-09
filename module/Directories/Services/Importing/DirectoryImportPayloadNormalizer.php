<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

final class DirectoryImportPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, string>
     */
    public function normalizeMapping(array $mapping): array
    {
        $result = [];

        foreach ($mapping as $column => $fieldKey) {
            if (!is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            $key = $this->normalizeColumnKey($column);
            if ($key !== '') {
                $result[$key] = $fieldKey;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public function normalizeFields(array $fields): array
    {
        return collect($fields)
            ->map(static fn(array $field): array => [
                'key' => is_string($field['key'] ?? null) ? $field['key'] : '',
                'name' => is_string($field['name'] ?? null) ? $field['name'] : '',
                'type' => is_string($field['type'] ?? null) ? $field['type'] : 'string',
                'nullable' => (bool)($field['nullable'] ?? true),
                'default' => $field['default'] ?? null,
                'sort_order' => is_int($field['sort_order'] ?? null) ? $field['sort_order'] : 0,
                'rules' => is_array($field['rules'] ?? null) ? $field['rules'] : ['nullable', 'string'],
            ])
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeIncomingRow(mixed $row): array
    {
        if ($row instanceof Collection) {
            /** @var array<string, mixed> $arr */
            $arr = $row->toArray();

            return $arr;
        }

        if (is_array($row)) {
            /** @var array<string, mixed> $arr */
            $arr = $row;

            return $arr;
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            /** @var array<string, mixed> $normalized */
            $normalized = $row->toArray();

            return $normalized;
        }

        /** @var array<string, mixed> $wrapped */
        $wrapped = Arr::wrap($row);

        return $wrapped;
    }

    /**
     * @param  array<string, mixed>  $rawRow
     * @param  Collection<string, string>  $mapping
     * @param  Collection<string, array<string, mixed>>  $fields
     * @return array<string, string|null>
     */
    public function prepareRow(array $rawRow, Collection $mapping, Collection $fields): array
    {
        $result = [];
        foreach ($fields->keys() as $fieldKey) {
            $result[$fieldKey] = null;
        }

        // Build a secondary lookup without underscores for matching when XLSX.js
        // and PhpSpreadsheet parse the same cell differently (spaces vs no spaces).
        $rowByStripped = [];
        foreach ($rawRow as $k => $v) {
            $key = $this->normalizeColumnKey($k);
            if ($key !== '') {
                $rowByStripped[str_replace('_', '', $key)] = $v;
            }
        }

        foreach ($mapping as $columnKey => $fieldKey) {
            $columnKey = $this->normalizeColumnKey($columnKey);
            $value = $rawRow[$columnKey]
                ?? $rowByStripped[str_replace('_', '', $columnKey)]
                ?? null;
            $result[$fieldKey] = $this->normalizeCellValue($value);
        }

        return $result;
    }

    private function normalizeCellValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $normalized = trim((string)$value);

            return $normalized === '' ? null : $normalized;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function normalizeColumnKey(mixed $column): string
    {
        if (is_string($column)) {
            $raw = $column;
        } elseif (is_int($column) || is_float($column) || is_bool($column)) {
            $raw = (string)$column;
        } else {
            return '';
        }

        $formatted = HeadingRowFormatter::format([$raw])[0] ?? null;

        return is_string($formatted) ? $formatted : Str::of($raw)->slug('_')->value();
    }
}
