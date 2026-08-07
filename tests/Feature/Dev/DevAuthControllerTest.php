<?php

declare(strict_types=1);

namespace Tests\Feature\Dev;

use App\Http\Controllers\Dev\DevAuthApiController;
use App\Http\Controllers\Dev\DevAuthWebController;
use App\Http\Middleware\AddApiMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Tests\TestCase;

final class DevAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/test/dev-auth/impersonate', [DevAuthApiController::class, 'impersonate'])
            ->middleware(AddApiMeta::class);
        Route::get('/test/dev-auth', DevAuthWebController::class);
    }

    public function test_page_returns_active_projects_with_their_non_system_users(): void
    {
        $project = $this->project('Рабочий проект');
        $user = $this->user($project, [
            'name' => 'Оператор',
            'login' => 'operator',
        ]);
        $this->user($project, [
            'is_system' => true,
        ]);
        $inactiveProject = $this->project('Неактивный проект', false);
        $this->user($inactiveProject);

        $this->get('/test/dev-auth')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/DevAuth')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->has('projects.0.users', 1)
                ->where('projects.0.users.0.id', $user->id));
    }

    public function test_impersonate_issues_launch_token_for_selected_user(): void
    {
        $project = $this->project();
        $user = $this->user($project);

        $this->postJson('/api/test/dev-auth/impersonate', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['_token', 'expires_in'],
                'meta' => ['timestamp', 'requestId'],
            ]);

        $this->assertDatabaseHas('launch_tokens', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_impersonate_rejects_user_from_another_project(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $user = $this->user($otherProject);

        $this->postJson('/api/test/dev-auth/impersonate', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/user_id');

        $this->assertDatabaseCount('launch_tokens', 0);
    }

    private function project(string $name = 'Проект', bool $isActive = true): Project
    {
        return Project::query()->create([
            'name' => $name,
            'sitekey' => 'sk-'.Str::random(8),
            'host' => Str::random(8).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => $isActive,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function user(Project $project, array $attributes = []): User
    {
        return User::query()->create([
            'name' => 'Пользователь',
            'email' => Str::random(12).'@example.test',
            'password' => Hash::make('password'),
            'project_id' => $project->id,
            'is_system' => false,
            ...$attributes,
        ]);
    }
}
