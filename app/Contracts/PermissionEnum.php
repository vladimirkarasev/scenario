<?php

declare(strict_types=1);

namespace App\Contracts;

interface PermissionEnum
{
    public function label(): string;

    public function group(): string;
}
