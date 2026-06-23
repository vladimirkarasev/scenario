<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
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

        /** @var Project|null $project */
        $project = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->first();

        if ($project === null) {
            return;
        }

        DB::table('project_users')->insertOrIgnore([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'project_id' => $project->id, 'role' => 'administrator'],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}
