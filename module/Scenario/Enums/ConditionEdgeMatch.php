<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ConditionEdgeMatch: string
{
    case Match = 'match';
    case Miss = 'miss';
    case Fallback = 'fallback';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'Совпадение',
            self::Miss => 'Нет совпадения',
            self::Fallback => 'Резервная ветка',
        };
    }
}
