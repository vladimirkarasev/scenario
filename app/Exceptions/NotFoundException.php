<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends DomainException
{
    #[\Override]
    public function status(): int
    {
        return 404;
    }

    public static function make(string $detail, string|\BackedEnum $code = 'NOT_FOUND', string $title = 'Не найдено'): self
    {
        return new self(self::normalizeCode($code), $title, $detail);
    }
}
