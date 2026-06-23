<?php

namespace Module\Users\Enums;

use App\Contracts\PermissionEnum;

enum GroupPermission: string implements PermissionEnum
{
    case View = 'group_view';
    case Create = 'group_create';
    case Delete = 'group_delete';

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
        return 'Группы';
    }
}
