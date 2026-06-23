<?php

declare(strict_types=1);

namespace Module\Directories\Exceptions;

use RuntimeException;

final class DirectoryExternalException extends RuntimeException
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

    public static function versionNotFound(): self
    {
        return new self(404, 'Active directory version not found.');
    }

    public static function proxyNotConfigured(): self
    {
        return new self(422, 'External directory has no proxy configured.');
    }

    public static function proxyNotFound(string $uuid): self
    {
        return new self(404, "Proxy endpoint [{$uuid}] not found.");
    }
}
