<?php

namespace Module\Categories\Enums;

use App\Contracts\PermissionEnum;

enum CategoryPermission: string implements PermissionEnum
{
    case View = 'category_view';
    case Create = 'category_create';
    case Delete = 'category_delete';

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
        return 'Категории';
    }
}
