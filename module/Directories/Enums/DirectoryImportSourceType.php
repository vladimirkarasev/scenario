<?php

declare(strict_types=1);

namespace Module\Directories\Enums;

enum DirectoryImportSourceType: string
{
    case File = 'file';
    case Remote = 'remote';
    case Proxy = 'proxy';

    public function label(): string
    {
        return match ($this) {
            self::File   => 'Файл',
            self::Remote => 'Удалённый',
            self::Proxy  => 'Прокси',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
