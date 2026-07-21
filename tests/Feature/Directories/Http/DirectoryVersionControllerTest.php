<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class DirectoryVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_versions(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $this->makeVersion($directory);
        $this->makeVersion($directory);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/versions")
            ->assertForbidden();
    }

    public function test_store_creates_new_version(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/versions", ['clone' => false])
            ->assertCreated();

        $this->assertSame(1, DirectoryVersion::query()->where('directory_id', $directory->id)->count());
    }

    public function test_store_clones_existing_version_schema(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);
        $original = $this->makeVersion($directory, schema: [['key' => 'name', 'name' => 'Name', 'type' => 'string']]);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/versions", ['clone' => true])
            ->assertCreated();

        $cloned = DirectoryVersion::query()
            ->where('directory_id', $directory->id)
            ->where('id', '!=', $original->id)
            ->first();

        $this->assertNotNull($cloned);
        $this->assertSame($original->schema_json, $cloned->schema_json);
    }

    public function test_store_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/versions", ['clone' => false])
            ->assertForbidden();
    }

    public function test_destroy_deletes_inactive_version(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_delete');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: false);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/versions/{$version->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('directory_versions', ['id' => $version->id]);
    }

    public function test_destroy_fails_for_active_version(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_delete');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/versions/{$version->id}")
            ->assertStatus(422);
    }

    public function test_update_schema_persists_fields(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}/versions/{$version->id}/schema", [
                'schema' => [
                    ['key' => 'city', 'name' => 'Город', 'type' => 'string'],
                ],
            ])
            ->assertOk();

        $this->assertSame('city', $version->fresh()->schema_json[0]['key']);
    }

    public function test_activate_makes_version_active(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_activate');
        $directory = $this->makeDirectory($project);
        $v1 = $this->makeVersion($directory, isActive: true, versionNumber: 1);
        $v2 = $this->makeVersion($directory, isActive: false, versionNumber: 2);

        $this->actingAs($user)
            ->patchJson("/api/directories/{$directory->id}/versions/{$v2->id}/activate")
            ->assertOk();

        $this->assertTrue((bool)$v2->fresh()->is_active);
        $this->assertFalse((bool)$v1->fresh()->is_active);
    }

    public function test_activate_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);

        $this->actingAs($user)
            ->patchJson("/api/directories/{$directory->id}/versions/{$version->id}/activate")
            ->assertForbidden();
    }

    public function test_update_settings_changes_source_type(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);

        $this->actingAs($user)
            ->patchJson("/api/directories/{$directory->id}/versions/{$version->id}/settings", [
                'source_type' => 'excel',
            ])
            ->assertOk();

        $this->assertSame('excel', $version->fresh()->source_type);
    }

    public function test_update_settings_persists_other_option(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);

        $this->actingAs($user)
            ->patchJson("/api/directories/{$directory->id}/versions/{$version->id}/settings", [
                'source_type' => 'manual',
                'allow_other' => true,
                'other_label' => 'Иное',
                'other_external_key' => 'other_code',
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.allow_other', true)
            ->assertJsonPath('data.attributes.other_label', 'Иное')
            ->assertJsonPath('data.attributes.other_external_key', 'other_code');

        $fresh = $version->fresh();
        $this->assertTrue($fresh->allow_other);
        $this->assertSame('Иное', $fresh->other_label);
        $this->assertSame('other_code', $fresh->other_external_key);
    }

    public function test_update_settings_clears_other_label_when_empty(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);
        $version->allow_other = true;
        $version->other_label = 'Старое';
        $version->save();

        $this->actingAs($user)
            ->patchJson("/api/directories/{$directory->id}/versions/{$version->id}/settings", [
                'source_type' => 'manual',
                'allow_other' => true,
                'other_label' => '',
            ])
            ->assertOk();

        $this->assertNull($version->fresh()->other_label);
    }

    public function test_update_settings_rejects_invalid_other_external_key(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_version_create');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);

        $this->actingAs($user)
            ->patchJson("/api/directories/{$directory->id}/versions/{$version->id}/settings", [
                'source_type' => 'manual',
                'other_external_key' => 'bad key!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('other_external_key');
    }

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $user = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return [$user, $project];
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'is_active' => true,
        ]);
    }

    private function makeDirectory(Project $project): Directory
    {
        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory '.Str::random(4),
            'slug' => 'dir-'.Str::random(6),
            'source_type' => 'manual',
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $schema */
    private function makeVersion(
        Directory $directory,
        bool $isActive = false,
        int $versionNumber = 0,
        array $schema = []
    ): DirectoryVersion {
        if ($versionNumber === 0) {
            $versionNumber = (int)DirectoryVersion::query()
                    ->where('directory_id', $directory->id)
                    ->max('version_number') + 1;
        }

        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => $versionNumber,
            'is_active' => $isActive,
            'source_type' => 'manual',
            'status' => $isActive ? 'active' : 'draft',
            'schema_json' => $schema,
        ]);
    }
}
