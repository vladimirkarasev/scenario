<?php

namespace Module\Projects\Enums;

use App\Contracts\PermissionEnum;

enum ProjectPermission: string implements PermissionEnum
{
    case View = 'project_view';
    case Create = 'project_create';
    case Delete = 'project_delete';

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
        return 'Проекты';
    }
}
