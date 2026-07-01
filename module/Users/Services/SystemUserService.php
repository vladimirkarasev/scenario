<?php

declare(strict_types=1);

namespace Module\Users\Services;

use Module\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Enums\SystemRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Module\Users\Enums\UserPermission;
use Module\Users\Repositories\RoleRepository;
use Module\Users\Repositories\UserRepository;
use Throwable;

final readonly class SystemUserService
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
    ) {
    }

    /**
     * @param  Project  $project
     * @return User
     * @throws Throwable
     */
    public function provision(Project $project): User
    {
        return DB::transaction(function () use ($project): User {
            $user = $this->users->firstOrCreateByExternalId(
                $project->id,
                'system:'.$project->id,
                [
                    'name' => 'Системный пользователь · '.$project->name,
                    'email' => 'system+'.$project->id.'@system.local',
                    'password' => Hash::make(Str::random(40)),
                    'is_system' => true,
                ],
            );

            if (!$user->is_system) {
                $user->forceFill(['is_system' => true])->save();
            }

            $role = $this->roles->firstOrCreateByName(
                SystemRole::ProjectService->value,
                [
                    'title' => 'Системная интеграция',
                    'description' => 'Сервисная роль проекта.',
                    'is_system' => true,
                ],
            );
            $role->forceFill(['is_system' => true])->save();
            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', [
                        UserPermission::View->value,
                        UserPermission::Create->value,
                        UserPermission::Update->value,
                        UserPermission::Impersonate->value,
                    ])
                    ->get()
            );

            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            return $user;
        });
    }

    public function find(Project $project): ?User
    {
        return $this->users->findSystem($project->id);
    }
}
