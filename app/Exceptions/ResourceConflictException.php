<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ResourceConflictException extends DomainException
{
    #[\Override]
    public function status(): int
    {
        return 409;
    }

    public static function make(
        string $detail,
        string|\BackedEnum $code = 'CONFLICT',
        string $title = 'Конфликт ресурсов',
    ): self {
        return new self(self::normalizeCode($code), $title, $detail);
    }
}
