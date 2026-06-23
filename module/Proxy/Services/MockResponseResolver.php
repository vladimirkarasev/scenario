<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Support\Arr;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Models\ProxyEndpoint;

final readonly class MockResponseResolver
{
    /**
     * Подбирает мок-ответ для endpoint-а в режиме мока.
     * Берёт первый вариант, чьи поля `match` совпадают со значениями normalized data.
     * Если ни один не подошёл — берёт первый вариант без `match` (default).
     *
     * @param  array<string, mixed>  $normalizedData
     */
    public function resolve(ProxyEndpoint $endpoint, array $normalizedData): ?ProxyResponse
    {
        $variants = $endpoint->mock_responses ?? [];

        if ($variants === []) {
            return null;
        }

        $default = null;

        foreach ($variants as $variant) {
            $match = $this->stringKeyed($variant['match'] ?? null);

            if ($match === []) {
                $default ??= $variant;

                continue;
            }

            if ($this->matches($match, $normalizedData)) {
                return $this->build($variant);
            }
        }

        if ($default !== null) {
            return $this->build($default);
        }

        return $this->build($variants[0]);
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $data
     */
    private function matches(array $match, array $data): bool
    {
        foreach ($match as $key => $expected) {
            $actual = Arr::get($data, $key);

            if ($actual != $expected) { // нестрогое сравнение: "1" совпадает с 1
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $variant */
    private function build(array $variant): ProxyResponse
    {
        $statusRaw = $variant['status'] ?? 200;
        $status = is_numeric($statusRaw) ? (int)$statusRaw : 200;

        $body = $this->stringKeyed($variant['body'] ?? null);
        $headers = $this->stringKeyed($variant['headers'] ?? null);
        $headers['X-Proxy-Mock'] = '1';

        return new ProxyResponse($status, $body, $headers);
    }

    /** @return array<string, mixed> */
    private function stringKeyed(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string)$key] = $item;
        }

        return $result;
    }
}
