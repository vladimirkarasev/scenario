<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use RuntimeException;

final readonly class NodeDataReader
{
    /** @param  array<string, mixed>  $node */
    public function id(array $node): string
    {
        $id = $node['id'] ?? null;

        return is_string($id) ? $id : throw new RuntimeException('Node has no id.');
    }

    /** @param  array<string, mixed>  $node */
    public function type(array $node): string
    {
        $type = $node['type'] ?? null;

        return is_string($type) ? $type : throw new RuntimeException('Node has no type.');
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function data(array $node): array
    {
        $raw = $node['data'] ?? null;

        if (!is_array($raw)) {
            return [];
        }

        $result = [];

        foreach ($raw as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param  array<array-key, mixed>  $data */
    public function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /** @param  array<array-key, mixed>  $data */
    public function integer(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    /** @param  array<array-key, mixed>  $data */
    public function boolean(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return $value !== null ? (bool) $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<int>
     */
    public function integerList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (!is_int($item) && !is_numeric($item)) {
                continue;
            }

            $integer = (int) $item;

            if ($integer >= 0) {
                $result[] = $integer;
            }
        }

        return $result;
    }
}
