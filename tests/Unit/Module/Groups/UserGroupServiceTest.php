<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Groups;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Groups\DTO\GroupRegistrationData;
use Module\Groups\DTO\UserGroupData;
use Module\Groups\Models\UserGroup;
use Module\Groups\Services\UserGroupService;
use Module\Projects\CurrentProject;
use Module\Projects\Models\Project;
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

    /**
     * Создание группы — site_id берётся из CurrentProject.
     */
    public function test_create_sets_site_id_from_current_project(): void
    {
        $actor = User::factory()->create();
        $group = $this->service->create(
            new UserGroupData(name: 'Тест', slug: 'test', extId: null, description: null, isActive: true),
            $actor,
        );

        $this->assertSame($this->project->id, $group->site_id);
        $this->assertDatabaseHas('user_groups', [
            'id'      => $group->id,
            'name'    => 'Тест',
            'site_id' => $this->project->id,
        ]);
    }

    /**
     * Создание группы — created_by и updated_by устанавливаются из актора.
     */
    public function test_create_sets_actor_as_creator(): void
    {
        $actor = User::factory()->create();

        $group = $this->service->create(
            new UserGroupData(name: 'Новая', slug: 'new', extId: null, description: null, isActive: true),
            $actor,
        );

        $this->assertSame($actor->id, $group->created_by);
        $this->assertSame($actor->id, $group->updated_by);
    }

    /**
     * Обновление группы меняет имя в БД.
     */
    public function test_update_persists_new_name(): void
    {
        $actor = User::factory()->create();
        $group = $this->makeGroup();

        $this->service->update(
            new UserGroupData(name: 'Новое имя', slug: $group->slug, extId: null, description: null, isActive: true),
            $group,
            $actor,
        );

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'name' => 'Новое имя']);
    }

    /**
     * Удаление группы — запись исчезает из БД.
     */
    public function test_delete_removes_group(): void
    {
        $group = $this->makeGroup();

        $this->service->delete($group);

        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);
    }

    /**
     * addMember прикрепляет пользователя к группе.
     */
    public function test_add_member_attaches_user_to_group(): void
    {
        $group = $this->makeGroup();
        $user  = User::factory()->create();

        $this->service->addMember($group, $user);

        $this->assertTrue($group->members()->where('users.id', $user->id)->exists());
    }

    /**
     * Повторный addMember не дублирует участника.
     */
    public function test_add_member_is_idempotent(): void
    {
        $group = $this->makeGroup();
        $user  = User::factory()->create();

        $this->service->addMember($group, $user);
        $this->service->addMember($group, $user);

        $this->assertSame(1, $group->members()->where('users.id', $user->id)->count());
    }

    /**
     * removeMember отвязывает пользователя от группы.
     */
    public function test_remove_member_detaches_user_from_group(): void
    {
        $group = $this->makeGroup();
        $user  = User::factory()->create();
        $group->members()->syncWithoutDetaching([$user->id]);

        $this->service->removeMember($group, $user);

        $this->assertFalse($group->members()->where('users.id', $user->id)->exists());
    }

    /**
     * syncFromRegistration создаёт группу, если её ещё нет.
     */
    public function test_sync_from_registration_creates_group_when_not_exists(): void
    {
        $user = User::factory()->create();
        $data = new GroupRegistrationData(slug: 'new-group', name: 'Новая группа');

        $group = $this->service->syncFromRegistration($data, $user);

        $this->assertNotNull($group->id);
        $this->assertSame('new-group', $group->slug);
        $this->assertDatabaseHas('user_groups', ['slug' => 'new-group', 'name' => 'Новая группа']);
    }

    /**
     * syncFromRegistration обновляет имя, если группа уже существует с другим именем.
     */
    public function test_sync_from_registration_updates_name_when_changed(): void
    {
        $user  = User::factory()->create();
        $group = $this->makeGroup(slug: 'existing-group', name: 'Старое имя');

        $data = new GroupRegistrationData(slug: 'existing-group', name: 'Новое имя');
        $this->service->syncFromRegistration($data, $user);

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'name' => 'Новое имя']);
    }

    /**
     * syncFromRegistration добавляет пользователя как участника группы.
     */
    public function test_sync_from_registration_adds_user_as_member(): void
    {
        $user = User::factory()->create();
        $data = new GroupRegistrationData(slug: 'reg-group', name: 'Группа');

        $group = $this->service->syncFromRegistration($data, $user);

        $this->assertTrue($group->members()->where('users.id', $user->id)->exists());
    }

    /**
     * syncFromRegistration не затирает ext_id, если новый extId === null.
     */
    public function test_sync_from_registration_does_not_overwrite_ext_id_when_null(): void
    {
        $user  = User::factory()->create();
        $group = $this->makeGroup(slug: 'ext-group', extId: 'original-ext-id');

        $data = new GroupRegistrationData(slug: 'ext-group', name: $group->name);
        $this->service->syncFromRegistration($data, $user);

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'ext_id' => 'original-ext-id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name'          => 'Project',
            'sitekey'       => 'sk-' . Str::random(6),
            'host'          => Str::random(4) . '.local',
            'shared_secret' => Str::random(32),
            'is_active'     => true,
        ]);
    }

    private function makeGroup(string $slug = '', string $name = 'Group', ?string $extId = null): UserGroup
    {
        return UserGroup::query()->create([
            'name'      => $name,
            'slug'      => $slug ?: 'group-' . Str::random(6),
            'site_id'   => $this->project->id,
            'ext_id'    => $extId,
            'is_active' => true,
        ]);
    }
}
