<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\DTO\Roles\RoleActionData;
use App\DTO\Roles\RoleData;
use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

final class RoleService
{
    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return Role::query()
            ->withCount('users')
            ->with('permissions')
            ->when($search, static fn ($q) => $q->where(static fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('title', 'ilike', "%{$search}%")))
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page[number]');
    }

    public function create(RoleData $data): Role
    {
        $role = Role::query()->create([
            'name' => $data->name,
            'guard_name' => 'web',
            'title' => $data->title,
            'description' => $data->description,
        ]);

        if ($data->permissions !== []) {
            $role->syncPermissions($data->permissions);
        }

        $role->loadCount('users');
        $role->load('permissions');

        return $role;
    }

    public function update(RoleData $data, Role $role): Role
    {
        $role->update([
            'name' => $role->is_system ? $role->name : $data->name,
            'guard_name' => 'web',
            'title' => $data->title,
            'description' => $data->description,
        ]);

        $role->syncPermissions($data->permissions);

        $role->loadCount('users');
        $role->load('permissions');

        return $role;
    }

    public function delete(RoleActionData $data, Role $role): void
    {
        abort_if($role->is_system, 403, 'Системную роль нельзя удалить.');
        abort_if($role->users()->exists(), 422, 'Нельзя удалить роль, которая назначена пользователям.');

        $role->delete();
    }
}
