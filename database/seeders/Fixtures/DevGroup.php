<?php

declare(strict_types=1);

namespace Database\Seeders\Fixtures;

enum DevGroup: string
{
    case FirstLine = 'first-line';
    case SecondLine = 'second-line';

    public function label(): string
    {
        return match ($this) {
            self::FirstLine => 'Первая линия',
            self::SecondLine => 'Вторая линия',
        };
    }
}
