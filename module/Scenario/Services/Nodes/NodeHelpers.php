<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;

/** Типобезопасные помощники для работы с данными узла и прогона. */
trait NodeHelpers
{
    /** @param array<string, mixed> $node */
    private function nodeId(array $node): string
    {
        $id = $node['id'] ?? null;

        return is_string($id) ? $id : throw new \RuntimeException('Node has no id.');
    }

    /** @param array<string, mixed> $node */
    private function nodeType(array $node): string
    {
        $type = $node['type'] ?? null;

        return is_string($type) ? $type : throw new \RuntimeException('Node has no type.');
    }

    /**
     * Возвращает data-секцию узла с гарантированными строковыми ключами.
     *
     * @param  array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function nodeData(array $node): array
    {
        $raw = $node['data'] ?? null;

        if (! is_array($raw)) {
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

    /** Возвращает версию прогона или выбрасывает исключение, если она не загружена. */
    private function runVersion(ScenarioRun $run): ScenarioVersion
    {
        return $run->version ?? throw new \RuntimeException('Run version is not loaded.');
    }

    /** @param array<array-key, mixed> $data */
    private function strField(array $data, string $key, string $default = ''): string
    {
        $val = $data[$key] ?? null;

        return is_string($val) ? $val : $default;
    }

    /** @param array<array-key, mixed> $data */
    private function intField(array $data, string $key, int $default = 0): int
    {
        $val = $data[$key] ?? null;

        return is_int($val) ? $val : $default;
    }

    /** @param array<array-key, mixed> $data */
    private function boolField(array $data, string $key, bool $default = false): bool
    {
        $val = $data[$key] ?? null;

        return $val !== null ? (bool) $val : $default;
    }

    /**
     * @param  array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function arrayField(array $data, string $key): array
    {
        $val = $data[$key] ?? null;

        return is_array($val) ? $val : [];
    }
}
