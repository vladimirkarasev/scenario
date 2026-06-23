<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
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
    }
}
