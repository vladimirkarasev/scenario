<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Models\ProxyEndpoint;

final readonly class MockResponseResolver
{
    public function resolve(ProxyEndpoint $endpoint): ?ProxyResponse
    {
        $variants = $endpoint->mock_responses ?? [];

        if ($variants === []) {
            return null;
        }

        foreach ($variants as $variant) {
            if ($variant['is_active'] ?? false) {
                return $this->build($variant);
            }
        }

        return $this->build($variants[0]);
    }

    /** @param  array<string, mixed>  $variant */
    private function build(array $variant): ProxyResponse
    {
        $statusRaw = $variant['status'] ?? 200;
        $status = is_numeric($statusRaw) ? (int) $statusRaw : 200;

        $body = $this->stringKeyed($variant['body'] ?? null);
        $headers = $this->stringKeyed($variant['headers'] ?? null);
        $headers['X-Proxy-Mock'] = '1';

        return new ProxyResponse($status, $body, $headers);
    }

    /** @return array<string, mixed> */
    private function stringKeyed(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }
}
