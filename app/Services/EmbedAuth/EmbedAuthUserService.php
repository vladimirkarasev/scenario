<?php

declare(strict_types=1);

namespace App\Services\EmbedAuth;

use App\DTO\EmbedAuth\ProjectUserRegisterData;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Module\Groups\Services\UserGroupService;
use Module\Projects\Models\Project;

final class EmbedAuthUserService
{
    public function __construct(
        private readonly UserGroupService $groupService,
    ) {
    }

    public function syncUser(ProjectUserRegisterData $data, Project $project): User
    {
        return DB::transaction(function () use ($data, $project): User {
            $user = $this->findExistingUser($data);

            if ($user === null) {
                try {
                    $user = User::query()->create([
                        'name' => $data->name,
                        'email' => $data->email ?? $this->generateFakeEmail($data->login),
                        'login' => $data->login,
                        'external_id' => $data->externalId,
                        'password' => Hash::make(Str::random(32)),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $user = $this->findExistingUser($data);
                    if ($user === null) {
                        throw new \RuntimeException('Failed to create or find user after conflict.');
                    }
                }
            } else {
                $user->name = $data->name;
                $user->login = $data->login;
                if ($data->email !== null) {
                    $user->email = $data->email;
                }
                if ($data->externalId !== null) {
                    $user->external_id = $data->externalId;
                }
                $user->save();
            }

            $this->ensureProjectMembership($user, $project);
            $this->syncProjectRoles($user, $project, $data->roles);

            if ($data->group !== null) {
                $this->groupService->syncFromRegistration($data->group, $user);
            }

            return $user;
        });
    }

    private function findExistingUser(ProjectUserRegisterData $data): ?User
    {
        if ($data->externalId !== null) {
            $found = User::query()->where('external_id', $data->externalId)->first();
            if ($found !== null) {
                return $found;
            }
        }

        if ($data->email !== null) {
            $found = User::query()->where('email', $data->email)->first();
            if ($found !== null) {
                return $found;
            }
        }

        return User::query()->where('login', $data->login)->first();
    }

    private function ensureProjectMembership(User $user, Project $project): void
    {
        DB::table('project_users')->insertOrIgnore([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    private function syncProjectRoles(User $user, Project $project, array $roles): void
    {
        DB::table('project_user_roles')
            ->where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->delete();

        if ($roles === []) {
            return;
        }

        $now = now();
        $records = array_map(static fn(string $role): array => [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ], $roles);

        DB::table('project_user_roles')->insert($records);
    }

    private function generateFakeEmail(string $login): string
    {
        return 'embed-'.sha1($login).'@local.invalid';
    }
}
