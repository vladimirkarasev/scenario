<?php

namespace Module\Directories\Enums;

use App\Contracts\PermissionEnum;

enum DirectoryPermission: string implements PermissionEnum
{
    case View = 'directory_view';
    case Create = 'directory_create';
    case Delete = 'directory_delete';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Просмотр',
            self::Create => 'Создание/Редактирование',
            self::Delete => 'Удаление',
        };
    }

    public function group(): string
    {
        return 'Справочники';
    }
}
