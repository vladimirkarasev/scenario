<?php

declare(strict_types=1);

namespace Module\Groups\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Groups\DTO\GroupRegistrationData;
use Module\Groups\DTO\UserGroupData;
use Module\Groups\DTO\UserGroupIndexData;
use Module\Groups\Models\UserGroup;
use Module\Groups\Repositories\UserGroupRepository;
use Module\Projects\CurrentProject;

final readonly class UserGroupService
{
    public function __construct(
        private UserGroupRepository $groups,
        private CurrentProject $currentProject,
    ) {
    }

    /** @return LengthAwarePaginator<int, UserGroup> */
    public function paginate(UserGroupIndexData $filters): LengthAwarePaginator
    {
        return $this->groups->paginate($filters, $this->currentProject->id());
    }

    public function find(UserGroup $group): UserGroup
    {
        return $group->loadCount('members')->load(['createdBy', 'updatedBy']);
    }

    public function create(UserGroupData $data, ?User $actor): UserGroup
    {
        return $this->groups->create([
            'site_id' => $this->currentProject->id(),
            'name' => $data->name,
            'slug' => $data->slug,
            'ext_id' => $data->extId,
            'description' => $data->description,
            'is_active' => $data->isActive,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    public function update(UserGroupData $data, UserGroup $group, ?User $actor): UserGroup
    {
        return $this->groups->update($group, [
            'name' => $data->name,
            'slug' => $data->slug,
            'ext_id' => $data->extId,
            'description' => $data->description,
            'is_active' => $data->isActive,
            'updated_by' => $actor?->id,
        ]);
    }

    public function delete(UserGroup $group): void
    {
        $this->groups->delete($group);
    }

    public function addMember(UserGroup $group, User $user): void
    {
        $group->members()->syncWithoutDetaching([$user->id]);
    }

    public function removeMember(UserGroup $group, User $user): void
    {
        $group->members()->detach($user->id);
    }

    public function syncFromRegistration(GroupRegistrationData $data, User $user): UserGroup
    {
        $group = $this->groups->findBySlug($data->slug);

        if ($group === null) {
            $group = $this->groups->create([
                'name' => $data->name,
                'slug' => $data->slug,
                'ext_id' => $data->extId,
                'is_active' => true,
            ]);
        } else {
            $updates = [];
            if ($group->name !== $data->name) {
                $updates['name'] = $data->name;
            }
            if ($data->extId !== null && $group->ext_id !== $data->extId) {
                $updates['ext_id'] = $data->extId;
            }
            if ($updates !== []) {
                $this->groups->update($group, $updates);
            }
        }

        $group->members()->syncWithoutDetaching([$user->id]);

        return $group;
    }
}
