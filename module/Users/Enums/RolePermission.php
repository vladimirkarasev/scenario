<?php

namespace Module\Users\Enums;

use App\Contracts\PermissionEnum;

enum RolePermission: string implements PermissionEnum
{
    case View = 'role_view';
    case Create = 'role_create';
    case Delete = 'role_delete';

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
        return 'Роли';
    }
}
