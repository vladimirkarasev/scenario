<?php

declare(strict_types=1);

namespace Module\Directories\Exceptions;

use RuntimeException;

final class DirectoryVersionException extends RuntimeException
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

    public static function notFound(): self
    {
        return new self(404, 'Active directory version not found.');
    }

    public static function notBelongsToDirectory(): self
    {
        return new self(404);
    }

    public static function cannotDeleteActive(): self
    {
        return new self(422, 'Нельзя удалить активную версию.');
    }
}
