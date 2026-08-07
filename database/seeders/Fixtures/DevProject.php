<?php

declare(strict_types=1);

namespace Database\Seeders\Fixtures;

enum DevProject: string
{
    case Alfa = 'alfa';
    case Beta = 'beta';

    public function id(): string
    {
        return match ($this) {
            self::Alfa => '019e5d9f-86d8-7311-9f8b-fb1dfef34a71',
            self::Beta => '019e5d9f-86d8-7311-9f8b-fb1dfef34a72',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Alfa => 'Alfa',
            self::Beta => 'Beta',
        };
    }

    public function host(): string
    {
        return "{$this->value}.localhost";
    }

    public function sharedSecret(): string
    {
        return "{$this->value}-shared-secret-for-local-dev";
    }

    public function groupId(DevGroup $group): string
    {
        return match ($this) {
            self::Alfa => match ($group) {
                DevGroup::FirstLine => '019f1000-0000-7000-a000-000000000001',
                DevGroup::SecondLine => '019f1000-0000-7000-a000-000000000002',
            },
            self::Beta => match ($group) {
                DevGroup::FirstLine => '019f1000-0000-7000-b000-000000000001',
                DevGroup::SecondLine => '019f1000-0000-7000-b000-000000000002',
            },
        };
    }
}
