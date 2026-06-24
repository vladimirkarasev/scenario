<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final readonly class ProxyResponse
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $body = [],
        public array $headers = [],
    ) {}

    /** @param  array<string, mixed>  $body */
    public static function accepted(array $body = []): self
    {
        return new self(202, $body);
    }

    /** @param  array<string, mixed>  $body */
    public static function ok(array $body = []): self
    {
        return new self(200, $body);
    }

    public static function error(string $message, int $statusCode = 400): self
    {
        return new self($statusCode, ['message' => $message]);
    }

    public function withRequestId(string $requestId): self
    {
        return new self($this->statusCode, $this->body, [
            ...$this->headers,
            'X-Request-Id' => $requestId,
        ]);
    }
}
