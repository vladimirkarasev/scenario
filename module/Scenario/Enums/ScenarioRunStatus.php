<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioRunStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активный',
            self::Completed => 'Завершён',
            self::Failed => 'Ошибка',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
