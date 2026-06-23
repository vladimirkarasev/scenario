<?php

namespace Module\Directories\Enums;

use App\Contracts\PermissionEnum;

enum DirectoryVersionPermission: string implements PermissionEnum
{
    case Create = 'directory_version_create';
    case Delete = 'directory_version_delete';
    case Activate = 'directory_version_activate';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Версии: создание/редактирование',
            self::Delete => 'Версии: удаление',
            self::Activate => 'Версии: активация',
        };
    }

    public function group(): string
    {
        return 'Справочники';
    }
}
