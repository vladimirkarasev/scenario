<?php

declare(strict_types=1);

namespace Module\Users;

use App\Contracts\PermissionEnum;
use Module\Actions\Enums\ActionPermission;
use Module\Categories\Enums\CategoryPermission;
use Module\Directories\Enums\DirectoryPermission;
use Module\Directories\Enums\DirectoryVersionPermission;
use Module\Projects\Enums\ProjectPermission;
use Module\Proxy\Enums\ProxyPermission;
use Module\Scenario\Enums\ScenarioPermission;
use Module\Users\Enums\GroupPermission;
use Module\Users\Enums\RolePermission;
use Module\Users\Enums\UserPermission;

final class PermissionRegistry
{
    private function __construct() {}

    /**
     * @return class-string<\BackedEnum&PermissionEnum>[]
     */
    public static function list(): array
    {
        return [
            UserPermission::class,
            ProjectPermission::class,
            GroupPermission::class,
            RolePermission::class,
            ProxyPermission::class,
            ScenarioPermission::class,
            DirectoryPermission::class,
            DirectoryVersionPermission::class,
            CategoryPermission::class,
            ActionPermission::class,
        ];
    }
}
