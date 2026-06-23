<?php

namespace Module\Actions\Enums;

use App\Contracts\PermissionEnum;

enum ActionPermission: string implements PermissionEnum
{
    case View = 'action_view';
    case Create = 'action_create';
    case Delete = 'action_delete';

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
        return 'Действия';
    }
}
