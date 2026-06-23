<?php

declare(strict_types=1);

namespace Module\Proxy\Enums;

use App\Contracts\PermissionEnum;

enum ProxyPermission: string implements PermissionEnum
{
    case View = 'proxy_view';
    case Create = 'proxy_create';
    case Delete = 'proxy_delete';

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
        return 'Proxy';
    }
}
