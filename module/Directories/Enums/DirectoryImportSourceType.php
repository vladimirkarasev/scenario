<?php

declare(strict_types=1);

namespace Module\Directories\Enums;

enum DirectoryImportSourceType: string
{
    case File = 'file';
    case Proxy = 'proxy';

    public function label(): string
    {
        return match ($this) {
            self::File   => 'Файл',
            self::Proxy  => 'Прокси',
        };
    }
}
