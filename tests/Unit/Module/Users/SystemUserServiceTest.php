<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Enums\UserPermission;
use Module\Users\Services\SystemUserService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SystemUserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_is_idempotent_and_assigns_protected_service_role(): void
    {
        foreach (UserPermission::cases() as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }

        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(6).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
        $service = app(SystemUserService::class);

        $first = $service->provision($project);
        $second = $service->provision($project);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->is_system);
        $this->assertTrue($second->hasRole('project-service'));
        $this->assertTrue($second->can(UserPermission::Impersonate->value));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('roles', [
            'name' => 'project-service',
            'is_system' => true,
        ]);
    }
}
