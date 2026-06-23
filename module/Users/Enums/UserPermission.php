<?php

declare(strict_types=1);

namespace Module\Users\Enums;

use App\Contracts\PermissionEnum;

enum UserPermission: string implements PermissionEnum
{
    case View = 'user_view';
    case Create = 'user_create';
    case Delete = 'user_delete';

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
        return 'Пользователи';
    }
}
