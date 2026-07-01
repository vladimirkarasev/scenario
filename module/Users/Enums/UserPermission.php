<?php

declare(strict_types=1);

namespace Module\Users\Enums;

use App\Contracts\PermissionEnum;

enum UserPermission: string implements PermissionEnum
{
    case View = 'user_view';
    case Create = 'user_create';
    case Update = 'user_update';
    case Delete = 'user_delete';
    case TokenView = 'user_token_view';
    case TokenManage = 'user_token_manage';
    case Impersonate = 'user_impersonate';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Просмотр',
            self::Create => 'Создание',
            self::Update => 'Редактирование',
            self::Delete => 'Удаление',
            self::TokenView => 'Просмотр API-токенов',
            self::TokenManage => 'Управление API-токенами',
            self::Impersonate => 'Выпуск iframe-токена авторизации',
        };
    }

    public function group(): string
    {
        return 'Пользователи';
    }
}
