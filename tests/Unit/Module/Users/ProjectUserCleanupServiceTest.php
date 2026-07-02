<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Tests\TestCase;

final class ProjectUserCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_project_removes_users_and_their_access_tokens(): void
    {
        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(6).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
        $user = User::factory()->create(['project_id' => $project->id]);
        $token = $user->createToken('project-token')->accessToken;

        $project->delete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }
}
