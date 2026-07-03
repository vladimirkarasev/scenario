<?php

declare(strict_types=1);

namespace Module\Groups\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Groups\DTO\UserGroupIndexData;
use Module\Groups\Models\UserGroup;

final class UserGroupRepository
{
    /** @return LengthAwarePaginator<int, UserGroup> */
    public function paginate(UserGroupIndexData $filters, string $projectId): LengthAwarePaginator
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

    /** @param  array<string, mixed>  $attributes */
    public function firstOrCreateBySlug(
        string $projectId,
        string $slug,
        array $attributes,
    ): UserGroup {
        return UserGroup::query()->firstOrCreate(
            ['site_id' => $projectId, 'slug' => $slug],
            $attributes,
        );
    }

    public function findByExternalId(string $projectId, string $externalId): ?UserGroup
    {
        return UserGroup::query()
            ->forProject($projectId)
            ->where('ext_id', $externalId)
            ->first();
    }
}
