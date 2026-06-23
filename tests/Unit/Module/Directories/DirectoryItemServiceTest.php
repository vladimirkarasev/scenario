<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\DTO\DirectoryItemUpdateData;
use Module\Directories\DTO\DirectoryManualItemData;
use Module\Directories\Exceptions\DirectoryItemException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryItemService;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class DirectoryItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryItemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DirectoryItemService::class);
    }

    /**
     * Пытаемся обновить элемент из директории B через директорию A — ожидаем исключение
     */
    public function test_update_throws_when_item_does_not_belong_to_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);
        $item = $this->makeItem($version);

        $this->expectException(DirectoryItemException::class);

        $this->service->update(
            $directoryA,
            $item,
            new DirectoryItemUpdateData(
                data: [],
                matchBy: null,
                parentId: null,
            ),
        );
    }

    /**
     * Пытаемся удалить элемент из директории B через директорию A — ожидаем исключение
     */
    public function test_delete_throws_when_item_does_not_belong_to_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);
        $item = $this->makeItem($version);

        $this->expectException(DirectoryItemException::class);

        $this->service->delete($directoryA, $item);
    }

    /**
     * Создаём элемент в директории с активной версией — запись должна появиться в БД
     */
    public function test_create_adds_item_to_active_version(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $version->schema_json = [['key' => 'name', 'name' => 'Name', 'type' => 'string']];
        $version->save();

        $result = $this->service->create(
            $directory,
            new DirectoryManualItemData(
                data: ['name' => 'Test Item'],
                matchBy: null,
                parentId: null,
            ),
        );

        $this->assertSame('Test Item', $result['data']['name']);
        $this->assertSame(1, DirectoryItem::query()->where('directory_version_id', $version->id)->count());
    }

    /**
     * Удаляем элемент правильной директории — запись должна исчезнуть из БД
     */
    public function test_delete_removes_item(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $item = $this->makeItem($version);

        $this->service->delete($directory, $item);

        $this->assertNull(DirectoryItem::query()->find($item->id));
    }

    /**
     * Обновляем data_json элемента — новые данные должны сохраниться в БД
     */
    public function test_update_persists_changes(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $version->schema_json = [['key' => 'name', 'name' => 'Name', 'type' => 'string']];
        $version->save();
        $item = $this->makeItem($version, ['name' => 'Old']);

        $result = $this->service->update(
            $directory,
            $item,
            new DirectoryItemUpdateData(
                data: ['name' => 'New'],
                matchBy: null,
                parentId: null,
            ),
        );

        $this->assertSame('New', $result['data']['name']);
    }

    /**
     * allow_other + withOther → синтетический «Другой» добавляется последним
     * с сентинел-id и стабильным external_key.
     */
    public function test_items_appends_other_option_when_enabled(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $version->schema_json = [['key' => 'name', 'name' => 'Name', 'type' => 'string']];
        $version->allow_other = true;
        $version->save();
        $this->makeItem($version, ['name' => 'Реальный']);

        $items = $this->service->items(
            directory: $directory,
            versionId: (string) $version->id,
            withOther: true,
        );

        $this->assertCount(2, $items);

        $other = $items[array_key_last($items)];
        $this->assertSame(DirectoryItemService::OTHER_ITEM_ID, $other['id']);
        $this->assertSame(DirectoryItemService::OTHER_EXTERNAL_KEY, $other['external_key']);
        $this->assertSame('Другой', $other['data']['label']);
        $this->assertNull($other['parent_id']);
    }

    /**
     * Кастомные label и external_key версии должны попадать в «Другой».
     */
    public function test_items_other_option_uses_custom_label_and_key(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $version->schema_json = [['key' => 'name', 'name' => 'Name', 'type' => 'string']];
        $version->allow_other = true;
        $version->other_label = 'Иное';
        $version->other_external_key = 'other_code';
        $version->save();

        $items = $this->service->items(
            directory: $directory,
            versionId: (string) $version->id,
            withOther: true,
        );

        $this->assertCount(1, $items);
        $this->assertSame('Иное', $items[0]['data']['label']);
        $this->assertSame('other_code', $items[0]['external_key']);
    }

    /**
     * Без флага withOther «Другой» не добавляется (админский грид остаётся чистым).
     */
    public function test_items_omits_other_option_without_flag(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $version->allow_other = true;
        $version->save();
        $this->makeItem($version, ['name' => 'Реальный']);

        $items = $this->service->items(
            directory: $directory,
            versionId: (string) $version->id,
            withOther: false,
        );

        $this->assertCount(1, $items);
        $this->assertNotSame(DirectoryItemService::OTHER_ITEM_ID, $items[0]['id']);
    }

    /**
     * Если allow_other выключен — «Другой» не добавляется даже с флагом.
     */
    public function test_items_omits_other_option_when_disabled_on_version(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);
        $version->save();
        $this->makeItem($version, ['name' => 'Реальный']);

        $items = $this->service->items(
            directory: $directory,
            versionId: (string) $version->id,
            withOther: true,
        );

        $this->assertCount(1, $items);
    }

    private function makeDirectory(): Directory
    {
        $project = Project::query()->create([
            'name' => 'Test',
            'sitekey' => uniqid('sk_', true),
            'host' => 'localhost',
            'is_active' => true,
        ]);

        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Dir '.uniqid(),
            'slug' => 'dir-'.uniqid(),
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
            'schema_json' => [],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function makeItem(DirectoryVersion $version, array $data = []): DirectoryItem
    {
        return DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => $data,
            'search_text' => '',
        ]);
    }
}
