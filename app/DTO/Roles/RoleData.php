<?php

declare(strict_types=1);

namespace App\DTO\Roles;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

final readonly class RoleData
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $name,
        public bool $canManageRoles,
        public array $permissions,
        public ?string $title,
        public ?string $description,
    ) {}

    public static function fromRequest(Request $request, ?Role $role = null): self
    {
        $permissions = array_values(array_filter(
            is_array($request->input('permissions')) ? $request->input('permissions') : [],
            is_string(...),
        ));

        $title = (string) $request->string('title');
        $description = (string) $request->string('description');

        return new self(
            name: (string) $request->string('name'),
            canManageRoles: (bool) $request->user()?->getPermissionNames()->contains('role_create'),
            permissions: $permissions,
            title: $title !== '' ? $title : null,
            description: $description !== '' ? $description : null,
        );
    }
}
