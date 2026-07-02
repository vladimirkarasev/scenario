<?php

declare(strict_types=1);

namespace Module\Users\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Module\Projects\CurrentProject;
use Module\Groups\Models\UserGroup;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Module\Users\DTO\UserData;
use Module\Users\DTO\UserIndexData;
use Module\Users\Enums\SystemRole;
use Module\Users\Events\UserCreated;
use Module\Users\Events\UserDeleted;
use Module\Users\Events\UserUpdated;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use Module\Users\Enums\UserErrorCode;
use Module\Users\Repositories\UserRepository;
use Module\Users\Repositories\RoleRepository;
use Throwable;

final readonly class UserService
{
    public function __construct(
        private UserRepository $users,
        private CurrentProject $currentProject,
        private Dispatcher $events,
        private RoleRepository $roles,
        private UserAuthorizationService $authorization,
    ) {
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(UserIndexData $filters): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $this->projectId());
    }

    public function find(User $user): User
    {
        return $this->users->findInProject($user, $this->projectId());
    }

    public function create(User $actor, UserData $data): User
    {
        $projectId = $this->projectId();
        $roles = $this->resolveRoles($data->roles);

        if ($data->roles !== []) {
            $this->authorization->assertMayAssignRoles($actor, $roles);
        }

        $result = DB::transaction(function () use ($data, $projectId, $roles): User {
            $user = $this->users->create([
                'name' => $data->name,
                'fio' => $data->fio,
                'email' => $data->email,
                'login' => $data->login,
                'external_id' => $data->externalId,
                'password' => Hash::make($data->password ?? Str::random(40)),
                'project_id' => $projectId,
            ]);

            $user->syncRoles($roles);
            $user->groups()->sync($data->groupIds);

            return $this->users->find($user);
        });

        $this->events->dispatch(
            new UserCreated(
                $result,
                $data->roles,
                $result->groups
                    ->map(static fn(UserGroup $group): string => $group->id)
                    ->values()
                    ->all(),
                $actor->id,
                $projectId,
            )
        );

        return $result;
    }

    /**
     * @throws Throwable
     */
    public function update(User $actor, UserData $data, User $user): User
    {
        $projectId = $this->projectId();
        $roles = ($data->rolesProvided || $data->roles !== [])
            ? $this->resolveRoles($data->roles)
            : null;

        if ($roles !== null) {
            $this->authorization->assertMayAssignRoles($actor, $roles);
        }

        $result = DB::transaction(function () use ($actor, $data, $user, $projectId, $roles): User {
            $user = $this->users->findInProjectForUpdate($user, $projectId);

            if ($user->is_system) {
                throw ForbiddenException::make('Системного пользователя нельзя редактировать.', UserErrorCode::SystemUserImmutable);
            }

            $this->authorization->assertMayManage($actor, $user);

            $attributes = [
                'name' => $data->name,
                'fio' => $data->fio,
                'email' => $data->email,
                'login' => $data->login,
                'external_id' => $data->externalId,
            ];

            if ($data->password !== null) {
                $attributes['password'] = Hash::make($data->password);
            }

            $user = $this->users->update($user, $attributes);

            if ($roles !== null) {
                $roles->map(
                    static fn(Role $role): string => $role->name,
                )->all()
                    |> array_values(...)
                    |> (fn($x) => $this->assertAdministratorRemains($user, $x, $projectId));
                $user->syncRoles($roles);
            }

            if ($data->groupIdsProvided) {
                $user->groups()->sync($data->groupIds);
            }

            return $this->users->find($user);
        });

        $this->events->dispatch(
            new UserUpdated(
                $result,
                $result->roles
                    ->map(static fn(Role $role): string => $role->name)
                    ->values()
                    ->all(),
                $result->groups
                    ->map(static fn(UserGroup $group): string => $group->id)
                    ->values()
                    ->all(),
                $actor->id,
                $projectId,
            )
        );

        return $result;
    }

    /**
     * @throws Throwable
     */
    public function delete(User $actor, User $user): void
    {
        $projectId = $this->projectId();
        $userId = DB::transaction(function () use ($actor, $user, $projectId): int {
            $user = $this->users->findInProjectForUpdate($user, $projectId);

            if ($user->is_system) {
                throw ForbiddenException::make('Системного пользователя нельзя удалить.', UserErrorCode::SystemUserImmutable);
            }

            if ($actor->id === $user->id) {
                throw ConflictException::make('Нельзя удалить текущего пользователя.', UserErrorCode::SelfDeleteForbidden);
            }

            $this->authorization->assertMayManage($actor, $user);
            $this->assertAdministratorRemains($user, [], $projectId);

            $userId = $user->id;
            $this->users->delete($user);

            return $userId;
        });

        $this->events->dispatch(new UserDeleted($userId, $projectId, $actor->id));
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('User operations require a current project.');
    }

    /** @param  list<string>  $newRoleNames */
    private function assertAdministratorRemains(User $user, array $newRoleNames, string $projectId): void
    {
        if (
            $user->hasRole(SystemRole::Administrator->value)
            && !in_array(SystemRole::Administrator->value, $newRoleNames, true)
            && $this->users->administratorsInProjectForUpdate($projectId)->count() <= 1
        ) {
            throw ConflictException::make('В проекте должен остаться хотя бы один администратор.', UserErrorCode::LastAdministrator);
        }
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Role>
     */
    private function resolveRoles(array $names): Collection
    {
        $roles = $this->roles->findByNames($names);
        $resolvedNames = $roles
            ->map(static fn(Role $role): string => $role->name)
            ->values()
            ->all();
        $missing = array_diff($names, $resolvedNames);

        if ($missing !== []) {
            throw ConflictException::make(
                sprintf('Роль «%s» не найдена.', (string) reset($missing)),
                UserErrorCode::RoleNotFound,
            );
        }

        return $roles;
    }
}
