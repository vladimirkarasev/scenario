<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Groups;

use App\Exceptions\NotFoundException;
use App\Exceptions\ResourceConflictException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Groups\DTO\GroupRegistrationData;
use Module\Groups\DTO\UserGroupData;
use Module\Groups\Models\UserGroup;
use Module\Groups\Services\UserGroupService;
use Module\Projects\CurrentProject;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Tests\TestCase;

final class UserGroupServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserGroupService $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->app->instance(CurrentProject::class, new CurrentProject($this->project));
        $this->service = app(UserGroupService::class);
    }

    public function test_create_sets_site_id_from_current_project(): void
    {
        $actor = $this->makeUser();
        $group = $this->service->create(
            new UserGroupData(name: 'Тест', slug: 'test', extId: null, description: null, isActive: true),
            $actor,
        );

        $this->assertSame($this->project->id, $group->site_id);
        $this->assertDatabaseHas('user_groups', [
            'id' => $group->id,
            'name' => 'Тест',
            'site_id' => $this->project->id,
        ]);
    }

    public function test_create_sets_actor_as_creator(): void
    {
        $actor = $this->makeUser();

        $group = $this->service->create(
            new UserGroupData(name: 'Новая', slug: 'new', extId: null, description: null, isActive: true),
            $actor,
        );

        $this->assertSame($actor->id, $group->created_by);
        $this->assertSame($actor->id, $group->updated_by);
    }

    public function test_update_persists_new_name(): void
    {
        $actor = $this->makeUser();
        $group = $this->makeGroup();

        $this->service->update(
            new UserGroupData(name: 'Новое имя', slug: $group->slug, extId: null, description: null, isActive: true),
            $group,
            $actor,
        );

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'name' => 'Новое имя']);
    }

    public function test_delete_removes_group(): void
    {
        $group = $this->makeGroup();

        $this->service->delete($group);

        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);
    }

    public function test_add_member_attaches_user_to_group(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser();

        $this->service->addMember($group, $user->id);

        $this->assertTrue($group->members()->where('users.id', $user->id)->exists());
    }

    public function test_add_member_is_idempotent(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser();

        $this->service->addMember($group, $user->id);
        $this->service->addMember($group, $user->id);

        $this->assertSame(1, $group->members()->where('users.id', $user->id)->count());
    }

    public function test_add_member_throws_when_user_not_found(): void
    {
        $group = $this->makeGroup();

        try {
            $this->service->addMember($group, 999999);
            self::fail('Expected NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('GROUP_MEMBER_USER_NOT_FOUND', $e->errorCode);
        }
    }

    public function test_remove_member_detaches_user_from_group(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser();
        $group->members()->syncWithoutDetaching([$user->id]);

        $this->service->removeMember($group, $user);

        $this->assertFalse($group->members()->where('users.id', $user->id)->exists());
    }

    public function test_sync_from_registration_creates_group_when_not_exists(): void
    {
        $user = $this->makeUser();
        $data = new GroupRegistrationData(slug: 'new-group', name: 'Новая группа');

        $group = $this->service->syncFromRegistration($data, $user);

        $this->assertNotNull($group->id);
        $this->assertSame('new-group', $group->slug);
        $this->assertDatabaseHas('user_groups', [
            'slug' => 'new-group',
            'name' => 'Новая группа',
            'site_id' => $this->project->id,
        ]);
    }

    public function test_sync_from_registration_updates_name_when_changed(): void
    {
        $user = $this->makeUser();
        $group = $this->makeGroup(slug: 'existing-group', name: 'Старое имя');

        $data = new GroupRegistrationData(slug: 'existing-group', name: 'Новое имя');
        $this->service->syncFromRegistration($data, $user);

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'name' => 'Новое имя']);
    }

    public function test_sync_from_registration_adds_user_as_member(): void
    {
        $user = $this->makeUser();
        $data = new GroupRegistrationData(slug: 'reg-group', name: 'Группа');

        $group = $this->service->syncFromRegistration($data, $user);

        $this->assertTrue($group->members()->where('users.id', $user->id)->exists());
    }

    public function test_sync_from_registration_does_not_overwrite_ext_id_when_null(): void
    {
        $user = $this->makeUser();
        $group = $this->makeGroup(slug: 'ext-group', extId: 'original-ext-id');

        $data = new GroupRegistrationData(slug: 'ext-group', name: $group->name);
        $this->service->syncFromRegistration($data, $user);

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'ext_id' => 'original-ext-id']);
    }

    public function test_sync_from_registration_is_idempotent_for_same_slug(): void
    {
        $firstUser = $this->makeUser();
        $secondUser = $this->makeUser();
        $data = new GroupRegistrationData(slug: 'shared-registration', name: 'Группа');

        $first = $this->service->syncFromRegistration($data, $firstUser);
        $second = $this->service->syncFromRegistration($data, $secondUser);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            UserGroup::query()
                ->where('site_id', $this->project->id)
                ->where('slug', 'shared-registration')
                ->count(),
        );
        $this->assertSame(2, $first->members()->count());
    }

    public function test_sync_from_registration_returns_domain_conflict_for_duplicate_external_id(): void
    {
        $this->makeGroup(slug: 'existing-group', extId: 'external-42');

        try {
            $this->service->syncFromRegistration(
                new GroupRegistrationData(
                    slug: 'another-group',
                    name: 'Другая группа',
                    extId: 'external-42',
                ),
                $this->makeUser(),
            );
            self::fail('Expected ResourceConflictException.');
        } catch (ResourceConflictException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame('GROUP_EXTERNAL_ID_CONFLICT', $exception->errorCode);
        }
    }

    public function test_update_preserves_optional_fields_when_they_are_omitted(): void
    {
        $group = $this->makeGroup(extId: 'external-42');
        $group->description = 'Описание';
        $group->is_active = false;
        $group->save();

        $this->service->update(
            new UserGroupData(
                name: 'Новое имя',
                slug: $group->slug,
                extId: null,
                description: null,
                isActive: true,
                extIdProvided: false,
                descriptionProvided: false,
                isActiveProvided: false,
            ),
            $group,
            $this->makeUser(),
        );

        $group->refresh();
        $this->assertSame('external-42', $group->ext_id);
        $this->assertSame('Описание', $group->description);
        $this->assertFalse($group->is_active);
    }

    public function test_add_member_rejects_user_from_another_project(): void
    {
        $group = $this->makeGroup();
        $otherProject = $this->makeProject();
        $user = User::factory()->create(['project_id' => $otherProject->id]);

        $this->expectException(NotFoundException::class);

        $this->service->addMember($group, $user->id);
    }

    public function test_database_cascade_deletes_groups_with_project(): void
    {
        $group = $this->makeGroup();

        DB::table('projects')->where('id', $this->project->id)->delete();

        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);
    }

    public function test_database_requires_project_for_group(): void
    {
        $this->expectException(QueryException::class);

        UserGroup::query()->create([
            'name' => 'Orphan',
            'slug' => 'orphan',
            'is_active' => true,
        ]);
    }

    public function test_paginate_requires_current_project(): void
    {
        $this->app->instance(CurrentProject::class, new CurrentProject(null));
        $service = app(UserGroupService::class);

        $this->expectException(\LogicException::class);

        $service->paginate(new \Module\Groups\DTO\UserGroupIndexData(
            search: null,
            isActive: null,
            pagination: new \App\Support\Pagination(1, 20),
        ));
    }

    public function test_find_rejects_group_from_another_project(): void
    {
        $otherProject = $this->makeProject();
        $group = UserGroup::query()->create([
            'name' => 'Чужая группа',
            'slug' => 'foreign-group',
            'site_id' => $otherProject->id,
            'is_active' => true,
        ]);

        $this->expectException(NotFoundException::class);

        $this->service->find($group);
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['project_id' => $this->project->id]);
    }

    private function makeGroup(string $slug = '', string $name = 'Group', ?string $extId = null): UserGroup
    {
        return UserGroup::query()->create([
            'name' => $name,
            'slug' => $slug ?: 'group-'.Str::random(6),
            'site_id' => $this->project->id,
            'ext_id' => $extId,
            'is_active' => true,
        ]);
    }
}
