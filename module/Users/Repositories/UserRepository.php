<?php

declare(strict_types=1);

namespace Module\Users\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Users\DTO\UserIndexData;
use Module\Users\QueryBuilders\UserBuilder;

final class UserRepository
{
    /** @return LengthAwarePaginator<int, User> */
    public function paginate(UserIndexData $filters, ?string $projectId = null): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->forProject($projectId)
            ->forGroups($filters->groupIds)
            ->forRoles($filters->roleIds)
            ->search($filters->search)
            ->paginate($filters->perPage, ['*'], 'page[number]');
    }

    public function find(User $user): User
    {
        return $user->load(['groups:id,name,slug', 'roles:id,name,title']);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    private function baseQuery(): UserBuilder
    {
        /** @var UserBuilder $query */
        $query = User::query()->with(['groups:id,name,slug', 'roles:id,name,title']);

        return $query;
    }
}
