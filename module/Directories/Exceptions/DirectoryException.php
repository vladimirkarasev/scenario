<?php

declare(strict_types=1);

namespace Module\Directories\Exceptions;

use RuntimeException;

final class DirectoryException extends RuntimeException
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

    public static function projectNotFound(): self
    {
        return new self(404, 'Project not found.');
    }

    public static function notInProject(): self
    {
        return new self(404);
    }

    public static function forbidden(): self
    {
        return new self(403);
    }
}
