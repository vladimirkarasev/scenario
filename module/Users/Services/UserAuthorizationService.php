<?php

declare(strict_types=1);

namespace Module\Users\Services;

use App\Exceptions\ForbiddenException;
use Illuminate\Support\Collection;
use Module\Users\Enums\RolePermission;
use Module\Users\Enums\SystemRole;
use Module\Users\Enums\UserErrorCode;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;

final class UserAuthorizationService
{
    public function assertMayManage(User $actor, User $target): void
    {
        if ($actor->hasRole(SystemRole::Administrator->value)) {
            return;
        }

        if ($target->hasRole(SystemRole::Administrator->value)) {
            throw ForbiddenException::make('Нельзя управлять администратором проекта.', UserErrorCode::ManageAdminForbidden);
        }

        $actorPermissions = collect($this->permissionNames($actor));
        $targetPermissions = collect($this->permissionNames($target));

        if ($targetPermissions->diff($actorPermissions)->isNotEmpty()) {
            throw ForbiddenException::make('Нельзя управлять более привилегированным пользователем.', UserErrorCode::ManagePrivilegedForbidden);
        }
    }

    /** @param Collection<int, Role> $roles */
    public function assertMayAssignRoles(User $actor, Collection $roles): void
    {
        if (!$actor->can(RolePermission::Create->value)) {
            throw ForbiddenException::make('Недостаточно прав для назначения ролей.', UserErrorCode::RoleAssignForbidden);
        }

        if ($actor->hasRole(SystemRole::Administrator->value)) {
            return;
        }

        $actorPermissions = collect($this->permissionNames($actor));

        foreach ($roles as $role) {
            if (
                $role->name === SystemRole::Administrator->value
                || $role->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty()
            ) {
                throw ForbiddenException::make('Нельзя назначить роль выше собственных полномочий.', UserErrorCode::RoleEscalationForbidden);
            }
        }
    }

    /** @param list<string> $permissions */
    public function assertMayGrantPermissions(User $actor, array $permissions): void
    {
        if ($actor->hasRole(SystemRole::Administrator->value)) {
            return;
        }

        $actorPermissions = $this->permissionNames($actor);

        if (array_diff($permissions, $actorPermissions) !== []) {
            throw ForbiddenException::make('Нельзя выдать разрешения выше собственных полномочий.', UserErrorCode::PermissionEscalationForbidden);
        }
    }

    public function assertMayManageRole(User $actor, Role $role): void
    {
        if ($actor->hasRole(SystemRole::Administrator->value)) {
            return;
        }

        $actorPermissions = collect($this->permissionNames($actor));

        if ($role->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty()) {
            throw ForbiddenException::make('Нельзя изменять роль выше собственных полномочий.', UserErrorCode::RoleManageForbidden);
        }
    }

    /** @return list<string> */
    private function permissionNames(User $user): array
    {
        $names = [];

        foreach ($user->getAllPermissions() as $permission) {
            if (!$permission instanceof Permission) {
                throw new \UnexpectedValueException('Permission collection contains an invalid model.');
            }

            $names[] = $permission->name;
        }

        return $names;
    }
}
