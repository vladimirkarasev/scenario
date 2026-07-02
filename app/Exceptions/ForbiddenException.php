<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ForbiddenException extends DomainException
{
    #[\Override]
    public function status(): int
    {
        return 403;
    }

    public static function make(string $detail, string|\BackedEnum $code = 'FORBIDDEN', string $title = 'Доступ запрещён'): self
    {
        return new self(self::normalizeCode($code), $title, $detail);
    }
}
