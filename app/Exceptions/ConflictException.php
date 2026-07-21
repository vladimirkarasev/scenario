<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ConflictException extends DomainException
{
    #[\Override]
    public function status(): int
    {
        return 422;
    }

    public static function make(string $detail, string|\BackedEnum $code = 'UNPROCESSABLE', string $title = 'Некорректный запрос'): self
    {
        return new self(self::normalizeCode($code), $title, $detail);
    }
}
