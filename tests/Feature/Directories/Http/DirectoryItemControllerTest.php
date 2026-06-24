<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты контроллера элементов справочника.
 * Роуты: GET|POST /api/directories/{directory}/items
 *        PUT|DELETE /api/directories/{directory}/items/{item}
 *        DELETE /api/directories/{directory}/items (bulk)
 */
final class DirectoryItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/directories/{directory}/items
    // -------------------------------------------------------------------------

    /**
     * Список элементов активной версии — data содержит все записи.
     */
    public function test_index_returns_items(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Alpha']);
        $this->makeItem($version, ['name' => 'Beta']);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * filter[version_id] ограничивает выборку конкретной версией.
     */
    public function test_index_filters_by_version_id(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $v1 = $this->makeVersion($directory, isActive: true, versionNumber: 1);
        $v2 = $this->makeVersion($directory, isActive: false, versionNumber: 2);
        $this->makeItem($v1, ['name' => 'Item V1']);
        $this->makeItem($v2, ['name' => 'Item V2']);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?filter[version_id]={$v1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Без пермишена directory_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/directories/{directory}/items (manual)
    // -------------------------------------------------------------------------

    /**
     * Создание элемента через manual-вставку — 201, запись в БД.
     */
    public function test_store_creates_item_in_active_version(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);
        $this->makeVersion($directory, isActive: true, schema: [
            ['key' => 'name', 'name' => 'Название', 'type' => 'string'],
        ]);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/items", [
                'data' => ['name' => 'Новый элемент'],
            ])
            ->assertCreated();

        $this->assertSame(1, DirectoryItem::query()->count());
    }

    /**
     * Без пермишена directory_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $this->makeVersion($directory, isActive: true);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/items", ['data' => []])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PUT /api/directories/{directory}/items/{item}
    // -------------------------------------------------------------------------

    /**
     * Обновление data_json элемента сохраняется в БД.
     */
    public function test_update_persists_item_data(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true, schema: [
            ['key' => 'name', 'name' => 'Name', 'type' => 'string'],
        ]);
        $item = $this->makeItem($version, ['name' => 'Старое']);

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}/items/{$item->id}", [
                'data' => ['name' => 'Новое'],
            ])
            ->assertOk();

        $this->assertSame('Новое', $item->fresh()->data_json['name']);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/directories/{directory}/items/{item}
    // -------------------------------------------------------------------------

    /**
     * Удаление элемента — 204, запись исчезает из БД.
     */
    public function test_destroy_deletes_item(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_delete');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $item = $this->makeItem($version);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/items/{$item->id}")
            ->assertNoContent();

        $this->assertNull(DirectoryItem::query()->find($item->id));
    }

    // -------------------------------------------------------------------------
    // DELETE /api/directories/{directory}/items (bulk)
    // -------------------------------------------------------------------------

    /**
     * Массовое удаление по ids — указанные элементы удаляются, остальные остаются.
     */
    public function test_bulk_destroy_deletes_specified_items(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_delete');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $a = $this->makeItem($version);
        $b = $this->makeItem($version);
        $c = $this->makeItem($version);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/items", [
                'ids' => [$a->id, $b->id],
            ])
            ->assertNoContent();

        $this->assertNull(DirectoryItem::query()->find($a->id));
        $this->assertNull(DirectoryItem::query()->find($b->id));
        $this->assertNotNull(DirectoryItem::query()->find($c->id));
    }

    /**
     * Пустой список ids — 204, ни один элемент не удаляется.
     */
    public function test_bulk_destroy_with_empty_ids_does_nothing(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_delete');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $item = $this->makeItem($version);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/items", ['ids' => []])
            ->assertNoContent();

        $this->assertNotNull(DirectoryItem::query()->find($item->id));
    }

    // -------------------------------------------------------------------------
    // Response shape / attributes
    // -------------------------------------------------------------------------

    /**
     * Каждый элемент имеет JSON:API-структуру: id, type = 'directory-item', attributes.
     */
    public function test_index_response_has_json_api_shape(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $item = $this->makeItem($version, ['name' => 'Alpha', 'code' => 'A1']);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items")
            ->assertOk()
            ->assertJsonPath('data.0.id', (string)$item->id)
            ->assertJsonPath('data.0.type', 'directory-item')
            ->assertJsonStructure(['data' => [['id', 'type', 'attributes']]])
            ->assertJsonPath('data.0.attributes.data.name', 'Alpha')
            ->assertJsonPath('data.0.attributes.data.code', 'A1');
    }

    /**
     * attributes содержит parent_id, external_key, created_at — помимо data.
     */
    public function test_index_attributes_include_top_level_fields(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'attributes' => ['parent_id', 'external_key', 'data', 'created_at'],
                    ]
                ],
            ]);
    }

    /**
     * Без активной версии (и вообще без версий) ответ — пустой массив data.
     */
    public function test_index_returns_empty_data_when_no_version_exists(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------------------------
    // fields[items] — sparse fieldsets
    // -------------------------------------------------------------------------

    /**
     * fields[items]=name возвращает в data только ключ name.
     */
    public function test_index_sparse_fieldsets_returns_only_requested_data_keys(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Alpha', 'code' => 'A1', 'level' => '3']);

        $response = $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?fields[items]=name")
            ->assertOk();

        $data = $response->json('data.0.attributes.data');

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('code', $data);
        $this->assertArrayNotHasKey('level', $data);
        $this->assertSame('Alpha', $data['name']);
    }

    /**
     * fields[items]=name,code возвращает оба поля, остальные — нет.
     */
    public function test_index_sparse_fieldsets_supports_multiple_keys(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Beta', 'code' => 'B2', 'level' => '1']);

        $response = $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?fields[items]=name,code")
            ->assertOk();

        $data = $response->json('data.0.attributes.data');

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayNotHasKey('level', $data);
    }

    /**
     * Без fields[items] возвращаются все поля data.
     */
    public function test_index_without_sparse_fieldsets_returns_all_data_keys(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Gamma', 'code' => 'G3', 'level' => '2']);

        $response = $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items")
            ->assertOk();

        $data = $response->json('data.0.attributes.data');

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('level', $data);
    }

    /**
     * fields[items] с несуществующим ключом возвращает пустой data объект.
     */
    public function test_index_sparse_fieldsets_with_nonexistent_key_returns_empty_data(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Delta']);

        $response = $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?fields[items]=nonexistent")
            ->assertOk();

        $this->assertSame([], $response->json('data.0.attributes.data'));
    }

    /**
     * fields[items] фильтрует data, но top-level атрибуты (parent_id, created_at) остаются.
     */
    public function test_index_sparse_fieldsets_keeps_top_level_attributes(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItem($version, ['name' => 'Epsilon', 'code' => 'E5']);

        $response = $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?fields[items]=name")
            ->assertOk();

        $attrs = $response->json('data.0.attributes');

        $this->assertArrayHasKey('parent_id', $attrs);
        $this->assertArrayHasKey('created_at', $attrs);
        $this->assertArrayHasKey('data', $attrs);
    }

    // -------------------------------------------------------------------------
    // filter[search] — полнотекстовый поиск
    // -------------------------------------------------------------------------

    /**
     * filter[search] возвращает элементы с совпадающим search_text.
     */
    public function test_index_filter_search_returns_matching_items(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItemWithSearchText($version, ['name' => 'Иванов Иван'], 'Иванов Иван');
        $this->makeItemWithSearchText($version, ['name' => 'Петров Пётр'], 'Петров Пётр');

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?filter[search]=Иванов")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.data.name', 'Иванов Иван');
    }

    /**
     * filter[search] без совпадений возвращает пустой список.
     */
    public function test_index_filter_search_returns_empty_when_no_match(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItemWithSearchText($version, ['name' => 'Сидоров'], 'Сидоров');

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?filter[search]=Кузнецов")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * filter[q] — устаревший алиас — также работает как полнотекстовый поиск.
     */
    public function test_index_filter_q_also_triggers_search(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory, isActive: true);
        $this->makeItemWithSearchText($version, ['name' => 'Москва'], 'Москва');
        $this->makeItemWithSearchText($version, ['name' => 'Казань'], 'Казань');

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/items?filter[q]=Москва")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
        int $versionNumber = 1,
        array $schema = []
    ): DirectoryVersion {
        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => $versionNumber,
            'is_active' => $isActive,
            'source_type' => 'manual',
            'status' => $isActive ? 'active' : 'draft',
            'schema_json' => $schema,
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

    /** @param  array<string, mixed>  $data */
    private function makeItemWithSearchText(DirectoryVersion $version, array $data, string $searchText): DirectoryItem
    {
        return DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => $data,
            'search_text' => $searchText,
        ]);
    }
}
