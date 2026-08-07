<?php

declare(strict_types=1);

namespace Module\Directories\Exceptions;

use RuntimeException;

final class DictionaryApiSyncException extends RuntimeException
{
    private function __construct(
        private readonly int $httpStatus,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    public static function notApiDirectory(): self
    {
        return new self(422, 'Directory source_type must be api.');
    }

    public static function proxyNotFound(string $uuid): self
    {
        return new self(422, "Proxy endpoint [{$uuid}] not found.");
    }

    public static function proxyNotConfigured(): self
    {
        return new self(422, 'API directory has no proxy configured.');
    }
}
