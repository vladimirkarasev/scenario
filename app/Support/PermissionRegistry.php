<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\PermissionEnum;
use BackedEnum;

final class PermissionRegistry
{
    /** @var array<class-string<BackedEnum&PermissionEnum>, class-string<BackedEnum&PermissionEnum>> */
    private static array $enums = [];

    /**
     * @param  class-string<BackedEnum&PermissionEnum>  ...$enums
     */
    public static function register(string ...$enums): void
    {
        foreach ($enums as $enum) {
            self::$enums[$enum] = $enum;
        }
    }

    /**
     * @return array<class-string<BackedEnum&PermissionEnum>>
     */
    public static function list(): array
    {
        return array_values(self::$enums);
    }
}
