<?php

declare(strict_types=1);

namespace Database\Seeders;

use Module\Users\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->updateOrCreate(
            ['name' => 'administrator', 'guard_name' => 'web'],
            [
                'title' => 'Администратор',
                'description' => 'Полный доступ ко всем функциям системы.',
                'is_system' => true,
            ],
        );

        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        $systemRole = Role::query()->updateOrCreate(
            ['name' => 'project-service', 'guard_name' => 'web'],
            [
                'title' => 'Системная интеграция',
                'description' => 'Сервисная роль проекта: управление пользователями и выпуск launch-токенов.',
                'is_system' => true,
            ],
        );

        $systemRole->syncPermissions(
            Permission::where('guard_name', 'web')
                ->whereIn('name', ['user_view', 'user_create', 'user_update', 'user_impersonate'])
                ->get(),
        );
    }
}
