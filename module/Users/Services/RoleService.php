<?php

declare(strict_types=1);

namespace Module\Users\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Module\Users\DTO\RoleData;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Module\Users\Repositories\RoleRepository;
use Module\Users\Events\SecurityEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class RoleService
{
    public function __construct(
        private RoleRepository $roles,
        private UserAuthorizationService $authorization,
        private Dispatcher $events,
    ) {
    }

    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->roles->paginate($perPage, $search);
    }

    public function create(User $actor, RoleData $data): Role
    {
        $this->authorization->assertMayGrantPermissions($actor, $data->permissions);

        $role = DB::transaction(function () use ($data): Role {
            $role = $this->roles->create([
                'name' => $data->name,
                'guard_name' => 'web',
                'title' => $data->title,
                'description' => $data->description,
            ]);

            $role->syncPermissions($data->permissions);

            return $role->loadCount('users')->load('permissions');
        });

        $this->events->dispatch(new SecurityEvent('role.created', $actor->id, [
            'role_id' => $role->id,
            'role' => $role->name,
            'permissions' => $data->permissions,
        ]));

        return $role;
    }

    public function update(User $actor, RoleData $data, Role $role): Role
    {
        $role = DB::transaction(function () use ($actor, $data, $role): Role {
            $role = $this->roles->findForUpdate($role);

            if ($role->is_system) {
                throw new HttpException(403, 'Системную роль нельзя редактировать.');
            }

            $this->authorization->assertMayManageRole($actor, $role);
            $this->authorization->assertMayGrantPermissions($actor, $data->permissions);

            $role->update([
                'name' => $data->name,
                'guard_name' => 'web',
                'title' => $data->title,
                'description' => $data->description,
            ]);

            $role->syncPermissions($data->permissions);

            return $role->loadCount('users')->load('permissions');
        });

        $this->events->dispatch(new SecurityEvent('role.updated', $actor->id, [
            'role_id' => $role->id,
            'role' => $role->name,
            'permissions' => $data->permissions,
        ]));

        return $role;
    }

    public function delete(User $actor, Role $role): void
    {
        $roleId = $role->id;
        $roleName = $role->name;

        DB::transaction(function () use ($actor, $role): void {
            $role = $this->roles->findForUpdate($role);

            if ($role->is_system) {
                throw new HttpException(403, 'Системную роль нельзя удалить.');
            }

            $this->authorization->assertMayManageRole($actor, $role);

            if ($role->users()->exists()) {
                throw new HttpException(422, 'Нельзя удалить роль, которая назначена пользователям.');
            }

            $role->delete();
        });

        $this->events->dispatch(new SecurityEvent('role.deleted', $actor->id, [
            'role_id' => $roleId,
            'role' => $roleName,
        ]));
    }

}
