<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\DTO;

use InvalidArgumentException;

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

    /** @param array<string, mixed> $config */
    public static function fromArray(string $name, array $config): self
    {
        $rawAuth = $config['auth'] ?? ['type' => 'none'];
        $rawHeaders = $config['headers'] ?? [];

        if (! is_array($rawAuth) || ! is_array($rawHeaders)) {
            throw new InvalidArgumentException('Gateway auth and headers config must be arrays.');
        }

        $baseUri = $config['base_uri'] ?? '';
        $timeout = $config['timeout'] ?? 10;
        $connectTimeout = $config['connect_timeout'] ?? 5;

        return new self(
            name: $name,
            baseUri: is_scalar($baseUri) ? (string) $baseUri : '',
            timeout: is_numeric($timeout) ? (float) $timeout : 10.0,
            connectTimeout: is_numeric($connectTimeout) ? (float) $connectTimeout : 5.0,
            mock: (bool) ($config['mock'] ?? false),
            auth: self::toStringKeyed($rawAuth),
            headers: self::toStringKeyed($rawHeaders),
        );
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
