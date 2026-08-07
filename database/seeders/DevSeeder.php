<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Fixtures\DevGroup;
use Database\Seeders\Fixtures\DevOperator;
use Database\Seeders\Fixtures\DevProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Module\Scenario\Enums\ScenarioPermission;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Module\Users\Services\SystemUserService;
use Spatie\Permission\Models\Permission;

final class DevSeeder extends Seeder
{
    public const string ADMIN_LOGIN = 'admin';

    public const string PASSWORD = 'password';

    private const string ADMINISTRATOR_ROLE = 'administrator';

    private const string OPERATOR_ROLE = 'operator';

    public function __construct(
        private readonly SystemUserService $systemUsers,
    ) {
    }

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $administratorRole = $this->administratorRole();
        $operatorRole = $this->operatorRole();

        DB::transaction(function () use ($administratorRole, $operatorRole): void {
            foreach (DevProject::cases() as $definition) {
                $this->seedProject($definition, $administratorRole, $operatorRole);
            }
        });
    }

    private function seedProject(
        DevProject $definition,
        Role $administratorRole,
        Role $operatorRole,
    ): void {
        $project = $this->upsertProject($definition);
        $administrator = $this->upsertAdministrator($project, $definition);
        $administrator->syncRoles([$administratorRole]);

        foreach (DevGroup::cases() as $group) {
            $this->upsertGroup($project, $definition, $group, $administrator);
        }

        foreach (DevOperator::cases() as $operator) {
            $user = $this->upsertOperator($project, $definition, $operator);
            $user->syncRoles([$operatorRole]);
            $user->groups()->sync(
                array_map(
                    $definition->groupId(...),
                    $operator->groups(),
                ),
            );
        }
    }

    private function upsertProject(DevProject $definition): Project
    {
        $project = Project::query()->updateOrCreate(
            ['id' => $definition->id()],
            [
                'name' => $definition->label(),
                'sitekey' => $definition->value,
                'host' => $definition->host(),
                'shared_secret' => $definition->sharedSecret(),
                'is_active' => true,
            ],
        );

        $this->systemUsers->provision($project);

        return $project;
    }

    private function upsertAdministrator(Project $project, DevProject $definition): User
    {
        return $this->upsertUser(
            project: $project,
            login: self::ADMIN_LOGIN,
            email: "admin@{$definition->value}.local",
            name: "Администратор {$definition->label()}",
            externalId: "{$definition->value}-admin",
        );
    }

    private function upsertOperator(
        Project $project,
        DevProject $definition,
        DevOperator $operator,
    ): User {
        $login = $operator->login();

        return $this->upsertUser(
            project: $project,
            login: $login,
            email: "{$login}@{$definition->value}.local",
            name: "Оператор {$definition->label()} {$operator->value}",
            externalId: "{$definition->value}-{$login}",
        );
    }

    private function upsertUser(
        Project $project,
        string $login,
        string $email,
        string $name,
        string $externalId,
    ): User {
        return User::query()->updateOrCreate(
            ['project_id' => $project->id, 'login' => $login],
            [
                'name' => $name,
                'fio' => $name,
                'email' => $email,
                'sitekey' => $project->sitekey,
                'host' => $project->host,
                'external_id' => $externalId,
                'is_system' => false,
                'password' => Hash::make(self::PASSWORD),
            ],
        );
    }

    private function upsertGroup(
        Project $project,
        DevProject $definition,
        DevGroup $group,
        User $administrator,
    ): void {
        UserGroup::query()->updateOrCreate(
            ['id' => $definition->groupId($group)],
            [
                'site_id' => $project->id,
                'name' => $group->label(),
                'slug' => $group->value,
                'description' => "Группа «{$group->label()}» проекта {$definition->label()}.",
                'is_active' => true,
                'created_by' => $administrator->id,
                'updated_by' => $administrator->id,
            ],
        );
    }

    private function administratorRole(): Role
    {
        return Role::query()
            ->where('name', self::ADMINISTRATOR_ROLE)
            ->where('guard_name', 'web')
            ->firstOrFail();
    }

    private function operatorRole(): Role
    {
        $role = Role::query()->updateOrCreate(
            ['name' => self::OPERATOR_ROLE, 'guard_name' => 'web'],
            [
                'title' => 'Оператор',
                'description' => 'Работа со сценариями и опросами в рамках назначенных групп.',
                'is_system' => false,
            ],
        );

        $role->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    ScenarioPermission::View->value,
                    ScenarioPermission::Dispatch->value,
                ])
                ->get(),
        );

        return $role;
    }
}
