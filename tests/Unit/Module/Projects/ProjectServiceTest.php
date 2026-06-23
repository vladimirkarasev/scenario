<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Projects;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\DTO\ProjectData;
use Module\Projects\Models\Project;
use Module\Projects\Services\ProjectService;
use Tests\TestCase;

final class ProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProjectService::class);
    }

    /**
     * Создание проекта сохраняет все атрибуты в БД.
     */
    public function test_create_persists_project_attributes(): void
    {
        $project = $this->service->create(new ProjectData(
            name: 'Тестовый проект',
            sitekey: 'sk-test01',
            host: 'test.local',
            sharedSecret: Str::random(32),
            isActive: true,
        ));

        $this->assertNotNull($project->id);
        $this->assertDatabaseHas('projects', [
            'id'       => $project->id,
            'name'     => 'Тестовый проект',
            'sitekey'  => 'sk-test01',
            'host'     => 'test.local',
        ]);
    }

    /**
     * Неактивный проект создаётся с is_active=false.
     */
    public function test_create_inactive_project(): void
    {
        $project = $this->service->create(new ProjectData(
            name: 'Отключённый',
            sitekey: 'sk-inactive',
            host: 'inactive.local',
            sharedSecret: Str::random(32),
            isActive: false,
        ));

        $this->assertFalse((bool) $project->is_active);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_active' => false]);
    }

    /**
     * Обновление меняет имя в БД.
     */
    public function test_update_persists_new_name(): void
    {
        $project = $this->makeProject('Старое имя');

        $this->service->update(new ProjectData(
            name: 'Новое имя',
            sitekey: $project->sitekey,
            host: $project->host,
            sharedSecret: Str::random(32),
            isActive: true,
        ), $project);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Новое имя']);
    }

    /**
     * Обновление меняет хост в БД.
     */
    public function test_update_changes_host(): void
    {
        $project = $this->makeProject();

        $this->service->update(new ProjectData(
            name: $project->name,
            sitekey: $project->sitekey,
            host: 'new-host.local',
            sharedSecret: Str::random(32),
            isActive: true,
        ), $project);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'host' => 'new-host.local']);
    }

    /**
     * Удаление проекта — запись исчезает из БД.
     */
    public function test_delete_removes_project_from_db(): void
    {
        $project = $this->makeProject();

        $this->service->delete($project);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    /**
     * paginate возвращает все проекты.
     */
    public function test_paginate_returns_all_projects(): void
    {
        $this->makeProject();
        $this->makeProject();
        $this->makeProject();

        $result = $this->service->paginate(20);

        $this->assertSame(3, $result->total());
    }

    /**
     * paginate учитывает параметр perPage.
     */
    public function test_paginate_respects_per_page(): void
    {
        $this->makeProject();
        $this->makeProject();
        $this->makeProject();

        $result = $this->service->paginate(2);

        $this->assertCount(2, $result->items());
        $this->assertSame(3, $result->total());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProject(string $name = 'Project'): Project
    {
        return Project::query()->create([
            'name'          => $name,
            'sitekey'       => 'sk-' . Str::random(6),
            'host'          => Str::random(4) . '.local',
            'shared_secret' => Str::random(32),
            'is_active'     => true,
        ]);
    }
}
