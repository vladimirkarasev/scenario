<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\DTO;

use Module\Proxy\Models\ProxyEndpoint;

final readonly class ApiGatewayConfig
{
    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public string $name,
        public string $baseUri,
        public float $timeout = 10.0,
        public float $connectTimeout = 5.0,
        public bool $mock = false,
        public array $auth = ['type' => 'none'],
        public array $headers = [],
    ) {}

    public static function fromEndpoint(ProxyEndpoint $endpoint): self
    {
        $credentials = $endpoint->credentials ?? [];

        return new self(
            name: $endpoint->code,
            baseUri: $endpoint->base_uri ?? '',
            mock: $endpoint->is_mocked,
            auth: self::resolveAuth($credentials),
            headers: ['Accept' => 'application/json'],
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private static function resolveAuth(array $credentials): array
    {
        if (filled($credentials['bearer_token'] ?? null)) {
            return ['type' => 'bearer', 'token' => $credentials['bearer_token']];
        }

        if (filled($credentials['username'] ?? null)) {
            return [
                'type' => 'basic',
                'username' => $credentials['username'],
                'password' => $credentials['password'] ?? '',
            ];
        }

        $headers = $credentials['headers'] ?? null;

        if (is_array($headers) && $headers !== []) {
            return ['type' => 'headers', 'headers' => self::toStringKeyed($headers)];
        }

        return ['type' => 'none'];
    }

    /**
     * @param  array<mixed, mixed>  $array
     * @return array<string, mixed>
     */
    private static function toStringKeyed(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
