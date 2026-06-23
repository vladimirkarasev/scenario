<?php

declare(strict_types=1);

namespace Module\Directories\Enums;

enum DirectoryImportMode: string
{
    case Create = 'create';
    case Update = 'update';
    case Replace = 'replace';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
