<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use App\Http\Middleware\LogHttpRequest;
use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class DirectoryDataControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(LogHttpRequest::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_returns_items_from_active_version(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory(slug: 'test-catalog');
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Alpha']);
        $this->makeItem($version, ['name' => 'Beta']);

        $this->actingAs($user)
            ->getJson('/api/directories/test-catalog/data')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_response_includes_dictionary_meta(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory(slug: 'meta-catalog');
        $this->makeVersion($directory, isActive: true);

        $response = $this->actingAs($user)
            ->getJson('/api/directories/meta-catalog/data')
            ->assertOk();

        $this->assertSame($directory->id, $response->json('meta.dictionary.id'));
        $this->assertSame('meta-catalog', $response->json('meta.dictionary.code'));
    }

    public function test_response_includes_resolved_related_field(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');

        $city = Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Cities',
            'slug' => 'cities',
            'source_type' => 'manual',
        ]);
        $cityVersion = $this->makeVersion($city, isActive: true);
        $cityItem = $this->makeItem($cityVersion, ['name' => 'Moscow']);

        $dealers = Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Dealers',
            'slug' => 'dealers',
            'source_type' => 'manual',
        ]);
        $dealerVersion = DirectoryVersion::query()->create([
            'directory_id' => $dealers->id,
            'version_number' => 1,
            'is_active' => true,
            'source_type' => 'manual',
            'status' => 'active',
            'schema_json' => [[
                'key' => 'city_id',
                'name' => 'City',
                'type' => 'related_directory',
                'related_directory_id' => $city->id,
                'related_match_key' => 'id',
                'related_template' => '{{ name }}',
            ]],
        ]);
        $this->makeItem($dealerVersion, ['city_id' => (string)$cityItem->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/directories/dealers/data')
            ->assertOk();

        $this->assertSame('Moscow', $response->json('data.0.attributes.related.city_id.label'));
    }

    public function test_returns_empty_data_when_no_items(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory(slug: 'empty-catalog');
        $this->makeVersion($directory, isActive: true);

        $this->actingAs($user)
            ->getJson('/api/directories/empty-catalog/data')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_404_when_no_active_version(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory(slug: 'inactive-catalog');
        $this->makeVersion($directory, isActive: false);

        $this->actingAs($user)
            ->getJson('/api/directories/inactive-catalog/data')
            ->assertNotFound();
    }

    public function test_returns_404_for_unknown_slug(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');

        $this->actingAs($user)
            ->getJson('/api/directories/non-existent-slug/data')
            ->assertNotFound();
    }

    public function test_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject();
        $directory = $this->makeDirectory(slug: 'perm-catalog');
        $this->makeVersion($directory, isActive: true);

        $this->actingAs($user)
            ->getJson('/api/directories/perm-catalog/data')
            ->assertForbidden();
    }

    public function test_returns_401_without_authentication(): void
    {
        $directory = $this->makeDirectory(slug: 'auth-catalog');
        $this->makeVersion($directory, isActive: true);

        $this->getJson('/api/directories/auth-catalog/data')
            ->assertUnauthorized();
    }

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'is_active' => true,
        ]);

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

    private function makeDirectory(string $slug = ''): Directory
    {
        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'is_active' => true,
        ]);

        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory',
            'slug' => $slug ?: 'dir-'.Str::random(6),
            'source_type' => 'manual',
        ]);
    }

    private function makeVersion(Directory $directory, bool $isActive = false): DirectoryVersion
    {
        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => $isActive,
            'source_type' => 'manual',
            'status' => $isActive ? 'active' : 'draft',
            'schema_json' => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function makeItem(DirectoryVersion $version, array $data = []): DirectoryItem
    {
        return DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => $data,
            'search_text' => implode(' ', array_values($data)),
        ]);
    }
}
