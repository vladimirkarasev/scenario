<?php

declare(strict_types=1);

namespace Database\Seeders;

use Module\Users\Models\Role;
use Module\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Module\Projects\Models\Project;

final class AdminUserSeeder extends Seeder
{
    public const string LOGIN = 'admin';

    public const string NAME = 'Admin';

    public const string EMAIL = 'admin@scenario.local';

    public const string PASSWORD = 'password';

    public const string ROLE = 'administrator';

    public function run(): void
    {
        /** @var Project|null $project */
        $project = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->first();

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL, 'project_id' => $project?->id],
            [
                'name' => 'Admin',
                'login' => self::LOGIN,
                'sitekey' => DemoProjectSeeder::SITEKEY,
                'host' => DemoProjectSeeder::HOST,
                'password' => Hash::make(self::PASSWORD),
            ],
        );

        $role = Role::query()->where('name', 'administrator')->first();

        if ($role !== null) {
            $user->syncRoles([$role]);
        }

    }
}
