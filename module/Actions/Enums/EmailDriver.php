<?php

declare(strict_types=1);

namespace Module\Actions\Enums;

enum EmailDriver: string
{
    case Smtp = 'smtp';
    case Proxy = 'proxy';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Proxy => 'Proxy (внешний сервис)',
        };
    }
}
