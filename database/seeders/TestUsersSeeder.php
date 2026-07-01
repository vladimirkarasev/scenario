<?php

declare(strict_types=1);

namespace Database\Seeders;

use Module\Users\Models\Role;
use Module\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Module\Projects\Enums\ProjectPermission;
use Module\Projects\Models\Project;
use Module\Groups\Enums\GroupPermission;
use Module\Users\Enums\RolePermission;
use Module\Users\Enums\UserPermission;
use Spatie\Permission\Models\Permission;

final class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->first();

        // Remove old test roles
        Role::query()->whereIn('name', ['viewer', 'manager'])->delete();

        $usersRole = $this->createRole('users', 'Пользователи', [
            UserPermission::View->value,
            UserPermission::Create->value,
            UserPermission::Delete->value,
        ]);

        $groupsRole = $this->createRole('groups', 'Группы', [
            GroupPermission::View->value,
            GroupPermission::Create->value,
            GroupPermission::Delete->value,
        ]);

        $projectsRole = $this->createRole('projects', 'Проекты', [
            ProjectPermission::View->value,
            ProjectPermission::Create->value,
            ProjectPermission::Delete->value,
        ]);

        $rolesRole = $this->createRole('roles', 'Роли', [
            RolePermission::View->value,
            RolePermission::Create->value,
            RolePermission::Delete->value,
        ]);

        $this->createUser('operator@scenario.local', 'operator', 'Operator', $usersRole, $project);
        $this->createUser('manager@scenario.local', 'manager', 'Manager', $groupsRole, $project);
        $this->createUser('projects@scenario.local', 'projects', 'Projects', $projectsRole, $project);
        $this->createUser('roles@scenario.local', 'roles', 'Roles', $rolesRole, $project);

        // Rename viewer → operator if exists
        User::query()->where('login', 'viewer')->update([
            'login' => 'operator',
            'name' => 'Operator',
            'email' => 'operator@scenario.local',
        ]);
    }

    private function createRole(string $name, string $title, array $permissions): Role
    {
        $role = Role::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['title' => $title, 'description' => '', 'is_system' => false],
        );

        $role->syncPermissions(
            Permission::whereIn('name', $permissions)->where('guard_name', 'web')->get(),
        );

        return $role;
    }

    private function createUser(string $email, string $login, string $name, Role $role, ?Project $project): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email, 'project_id' => $project?->id],
            [
                'name' => $name,
                'login' => $login,
                'sitekey' => DemoProjectSeeder::SITEKEY,
                'host' => DemoProjectSeeder::HOST,
                'password' => Hash::make('password'),
            ],
        );

        $user->syncRoles([$role]);
    }
}
