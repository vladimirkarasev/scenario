<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

use App\Contracts\PermissionEnum;

enum ScenarioPermission: string implements PermissionEnum
{
    case View = 'scenario_view';
    case Create = 'scenario_create';
    case Delete = 'scenario_delete';
    case Dispatch = 'scenario_dispatch';

    public function label(): string
    {
        return match ($this) {
            self::View     => 'Просмотр',
            self::Create   => 'Создание/Редактирование',
            self::Delete   => 'Удаление',
            self::Dispatch => 'Запуск опроса у пользователя',
        };
    }

    public function group(): string
    {
        return 'Сценарии';
    }
}
