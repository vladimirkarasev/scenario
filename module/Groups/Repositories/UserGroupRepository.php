<?php

declare(strict_types=1);

namespace Module\Groups\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Groups\DTO\UserGroupIndexData;
use Module\Groups\Models\UserGroup;

final class UserGroupRepository
{
    /** @return LengthAwarePaginator<int, UserGroup> */
    public function paginate(UserGroupIndexData $filters, ?string $projectId = null): LengthAwarePaginator
    {
        return UserGroup::query()
            ->forProject($projectId)
            ->withCount('members')
            ->with(['createdBy', 'updatedBy'])
            ->search($filters->search)
            ->active($filters->isActive)
            ->orderBy('name')
            ->paginate($filters->pagination->size, ['*'], 'page[number]', $filters->pagination->number);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): UserGroup
    {
        return UserGroup::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(UserGroup $group, array $attributes): UserGroup
    {
        $group->update($attributes);

        return $group;
    }

    public function delete(UserGroup $group): void
    {
        $group->delete();
    }

    public function findBySlug(string $slug): ?UserGroup
    {
        return UserGroup::query()->where('slug', $slug)->first();
    }
}
