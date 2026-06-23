<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Module\Users\PermissionRegistry;
use Spatie\Permission\Models\Permission;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::list() as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                Permission::firstOrCreate([
                    'name' => $case->value,
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}
