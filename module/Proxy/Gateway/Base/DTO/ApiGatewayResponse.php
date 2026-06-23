<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\DTO;

final readonly class ApiGatewayResponse
{
    /** @param array<string, mixed> $headers */
    public function __construct(
        public int $statusCode,
        public mixed $body = null,
        public array $headers = [],
    ) {}

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (! is_array($this->body)) {
            return $key === null ? [] : $default;
        }

        if ($key === null) {
            return $this->body;
        }

        return data_get($this->body, $key, $default);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp((string) $header, $key) === 0) {
                return $value;
            }
        }

        return $default;
    }
}
