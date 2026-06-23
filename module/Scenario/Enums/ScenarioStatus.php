<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft    => 'Черновик',
            self::Active   => 'Активный',
            self::Archived => 'Архив',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft    => 'slate',
            self::Active   => 'green',
            self::Archived => 'orange',
        };
    }
}
