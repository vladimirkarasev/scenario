<?php

declare(strict_types=1);

namespace Module\Directories\Exceptions;

use RuntimeException;

final class DirectoryItemException extends RuntimeException
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

    public static function notBelongsToDirectory(): self
    {
        return new self(404);
    }
}
