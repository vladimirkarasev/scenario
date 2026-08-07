<?php

declare(strict_types=1);

namespace Database\Seeders\Fixtures;

enum DevOperator: int
{
    case First = 1;
    case Second = 2;
    case Third = 3;

    public function login(): string
    {
        return "operator-{$this->value}";
    }

    /** @return list<DevGroup> */
    public function groups(): array
    {
        return match ($this) {
            self::First => [DevGroup::FirstLine],
            self::Second => [DevGroup::SecondLine],
            self::Third => [DevGroup::FirstLine, DevGroup::SecondLine],
        };
    }
}
