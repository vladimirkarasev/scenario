<?php

declare(strict_types=1);

namespace Module\Users\Repositories;

use App\Exceptions\NotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Users\DTO\UserIndexData;
use Module\Users\Enums\SystemRole;
use Module\Users\Enums\UserErrorCode;
use Module\Users\Models\User;
use Module\Users\QueryBuilders\UserBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

final class UserRepository
{
    /** @return LengthAwarePaginator<int, User> */
    public function paginate(UserIndexData $filters, string $projectId): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->forProject($projectId)
            ->forGroups($filters->groupIds)
            ->forRoles($filters->roleIds)
            ->search($filters->search)
            ->ordered()
            ->paginate($filters->pagination->size, ['*'], 'page[number]', $filters->pagination->number);
    }

    public function find(User $user): User
    {
        return $user->load(['groups:id,name,slug', 'roles:id,name,title']);
    }

    public function findInProject(User $user, string $projectId): User
    {
        return $this->baseQuery()
            ->forProject($projectId)
            ->whereKey($user->getKey())
            ->first() ?? throw $this->notFound();
    }

    public function findInProjectForUpdate(User $user, string $projectId): User
    {
        return $this->baseQuery()
            ->forProject($projectId)
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->first() ?? throw $this->notFound();
    }

    private function notFound(): NotFoundException
    {
        return NotFoundException::from(UserErrorCode::UserNotFound);
    }

    public function findNonSystemByExternalId(string $projectId, string $externalId): ?User
    {
        return User::query()
            ->forProject($projectId)
            ->notSystem()
            ->withExternalId($externalId)
            ->first();
    }

    /** @param array<string, mixed> $attributes */
    public function firstOrCreateByExternalId(
        string $projectId,
        string $externalId,
        array $attributes,
    ): User {
        return User::query()->firstOrCreate(
            ['project_id' => $projectId, 'external_id' => $externalId],
            $attributes,
        );
    }

    public function findSystem(string $projectId): ?User
    {
        return User::query()->forProject($projectId)->system()->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }

    /** @return Collection<int, User> */
    public function administratorsInProjectForUpdate(string $projectId): Collection
    {
        return User::query()
            ->forProject($projectId)
            ->withRoleName(SystemRole::Administrator->value)
            ->lockForUpdate()
            ->get();
    }

    /** @return LazyCollection<int, User> */
    public function inProjectLazily(string $projectId): LazyCollection
    {
        return User::query()->forProject($projectId)->lazyById();
    }

    private function baseQuery(): UserBuilder
    {
        /** @var UserBuilder $query */
        $query = User::query()->with(['groups:id,name,slug', 'roles.permissions']);

        return $query;
    }
}
