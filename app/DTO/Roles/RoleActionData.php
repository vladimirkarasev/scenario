<?php

declare(strict_types=1);

namespace App\DTO\Roles;

use Illuminate\Http\Request;

final readonly class RoleActionData
{
    public function __construct(
        public bool $canManageRoles,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            canManageRoles: (bool)$request->user()?->getPermissionNames()->contains('role_create'),
        );
    }
}
