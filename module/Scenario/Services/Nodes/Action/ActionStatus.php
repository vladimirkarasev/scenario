<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

enum ActionStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Выполняется',
            self::Success => 'Успешно',
            self::Failed => 'Ошибка',
            self::Done => 'Завершено',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'blue',
            self::Success => 'green',
            self::Failed => 'red',
            self::Done => 'slate',
        };
    }
}
