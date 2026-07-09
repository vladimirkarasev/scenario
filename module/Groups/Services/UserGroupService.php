<?php

declare(strict_types=1);

namespace Module\Groups\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ResourceConflictException;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Module\Groups\DTO\GroupRegistrationData;
use Module\Groups\DTO\UserGroupData;
use Module\Groups\DTO\UserGroupIndexData;
use Module\Groups\Enums\GroupErrorCode;
use Module\Groups\Models\UserGroup;
use Module\Groups\Repositories\UserGroupRepository;
use Module\Projects\CurrentProject;
use Module\Users\Models\User;

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
        return $this->groups->paginate($filters, $this->projectId());
    }

    public function find(UserGroup $group): UserGroup
    {
        $this->assertGroupInCurrentProject($group);

        return $group->loadCount('members')->load(['createdBy', 'updatedBy']);
    }

    public function create(UserGroupData $data, ?User $actor): UserGroup
    {
        return $this->groups->create([
            'site_id' => $this->projectId(),
            'name' => $data->name,
            'slug' => $data->slug,
            'ext_id' => $data->extId,
            'description' => $data->description,
            'is_active' => $data->isActive,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ])->loadCount('members');
    }

    public function update(UserGroupData $data, UserGroup $group, ?User $actor): UserGroup
    {
        $this->assertGroupInCurrentProject($group);

        $attributes = [
            'name' => $data->name,
            'slug' => $data->slug,
            'updated_by' => $actor?->id,
        ];

        if ($data->extIdProvided) {
            $attributes['ext_id'] = $data->extId;
        }
        if ($data->descriptionProvided) {
            $attributes['description'] = $data->description;
        }
        if ($data->isActiveProvided) {
            $attributes['is_active'] = $data->isActive;
        }

        return $this->groups->update($group, $attributes)->loadCount('members');
    }

    public function delete(UserGroup $group): void
    {
        $this->assertGroupInCurrentProject($group);
        $this->groups->delete($group);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function listMembers(UserGroup $group, Pagination $pagination): LengthAwarePaginator
    {
        $this->assertGroupInCurrentProject($group);

        return $group->members()
            ->getQuery()
            ->orderBy('name')
            ->paginate($pagination->size, ['*'], 'page[number]', $pagination->number);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function searchMemberCandidates(
        UserGroup $group,
        ?string $search,
        Pagination $pagination,
    ): LengthAwarePaginator
    {
        $this->assertGroupInCurrentProject($group);

        return User::query()
            ->forProject($this->projectId())
            ->whereDoesntHave(
                'groups',
                static fn(Builder $query) => $query->where('user_groups.id', $group->id),
            )
            ->search($search)
            ->ordered()
            ->paginate($pagination->size, ['*'], 'page[number]', $pagination->number);
    }

    public function addMember(UserGroup $group, int $userId): void
    {
        $this->assertGroupInCurrentProject($group);

        $user = User::query()
            ->forProject($this->projectId())
            ->find($userId)
            ?? throw NotFoundException::from(GroupErrorCode::MemberUserNotFound);

        $group->members()->syncWithoutDetaching([$user->id]);
    }

    public function removeMember(UserGroup $group, User $user): void
    {
        $this->assertGroupInCurrentProject($group);

        if ($user->project_id !== $this->projectId()) {
            throw NotFoundException::from(GroupErrorCode::MemberUserNotFound);
        }

        $group->members()->detach($user->id);
    }

    public function syncFromRegistration(GroupRegistrationData $data, User $user): UserGroup
    {
        $projectId = $this->projectId();
        if ($user->project_id !== $projectId) {
            throw NotFoundException::from(GroupErrorCode::MemberUserNotFound);
        }

        try {
            return DB::transaction(function () use ($data, $user, $projectId): UserGroup {
                $this->assertExternalIdAvailable($data, $projectId);

                $group = $this->groups->firstOrCreateBySlug(
                    $projectId,
                    $data->slug,
                    [
                        'name' => $data->name,
                        'ext_id' => $data->extId,
                        'is_active' => true,
                    ],
                );

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

                $group->members()->syncWithoutDetaching([$user->id]);

                return $group;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (
                $data->extId !== null
                && $this->groups->findByExternalId($projectId, $data->extId) !== null
            ) {
                throw ResourceConflictException::from(GroupErrorCode::ExternalIdConflict);
            }

            throw $exception;
        }
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Group operations require a current project.');
    }

    private function assertGroupInCurrentProject(UserGroup $group): void
    {
        if ($group->site_id !== $this->projectId()) {
            throw NotFoundException::from(GroupErrorCode::GroupNotFound);
        }
    }

    private function assertExternalIdAvailable(GroupRegistrationData $data, string $projectId): void
    {
        if ($data->extId === null) {
            return;
        }

        $group = $this->groups->findByExternalId($projectId, $data->extId);
        if ($group !== null && $group->slug !== $data->slug) {
            throw ResourceConflictException::from(GroupErrorCode::ExternalIdConflict);
        }
    }
}
