<?php

declare(strict_types=1);

namespace App\Exceptions\EmbedAuth;

use RuntimeException;

final class InvalidTokenException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
