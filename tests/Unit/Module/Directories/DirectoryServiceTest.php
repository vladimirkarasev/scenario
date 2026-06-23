<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class DirectoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DirectoryService::class);
    }

    /**
     * Директория принадлежит проекту A, передаём проект B — ожидаем исключение
     */
    public function test_ensure_project_access_throws_when_directory_belongs_to_other_project(): void
    {
        $projectA = $this->makeProject('sk-a');
        $projectB = $this->makeProject('sk-b');
        $directory = $this->makeDirectory($projectA);

        $this->expectException(DirectoryException::class);

        $this->service->ensureProjectAccess($directory, $projectB);
    }

    /**
     * Директория принадлежит правильному проекту — исключений не должно быть
     */
    public function test_ensure_project_access_passes_when_directory_belongs_to_project(): void
    {
        $project = $this->makeProject('sk-c');
        $directory = $this->makeDirectory($project);

        $this->service->ensureProjectAccess($directory, $project);

        $this->assertTrue(true); // no exception thrown
    }

    /**
     * Пользователь с sitekey/host, которых нет в БД — ожидаем исключение «Project not found»
     */
    public function test_current_project_for_user_throws_when_no_matching_project(): void
    {
        $user = User::factory()->create(['sitekey' => 'sk-unknown', 'host' => 'unknown.test']);

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('Project not found.');

        $this->service->currentProjectForUser($user);
    }

    /**
     * sitekey и host пользователя совпадают с проектом — должен вернуться тот же проект
     */
    public function test_current_project_for_user_returns_matching_project(): void
    {
        $project = $this->makeProject('sk-user', 'myapp.test');
        $user = User::factory()->create(['sitekey' => 'sk-user', 'host' => 'myapp.test']);

        $result = $this->service->currentProjectForUser($user);

        $this->assertSame($project->id, $result->id);
    }

    private function makeProject(string $sitekey, string $host = 'localhost'): Project
    {
        return Project::query()->create([
            'name' => 'Project '.$sitekey,
            'sitekey' => $sitekey,
            'host' => $host,
            'is_active' => true,
        ]);
    }

    private function makeDirectory(Project $project): Directory
    {
        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Dir '.uniqid(),
            'slug' => 'dir-'.uniqid(),
            'source_type' => 'manual',
        ]);
    }
}
