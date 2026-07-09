<?php

declare(strict_types=1);

namespace Module\Directories\Enums;

enum DirectoryImportMode: string
{
    case Create = 'create';
    case Update = 'update';
    case Replace = 'replace';

    public function label(): string
    {
        return match ($this) {
            self::Create  => 'Создание',
            self::Update  => 'Обновление',
            self::Replace => 'Замена',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
