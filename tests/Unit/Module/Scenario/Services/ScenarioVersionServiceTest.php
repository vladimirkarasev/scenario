<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Scenario\DTO\ScenarioVersionActionData;
use Module\Scenario\DTO\ScenarioVersionData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;
use Module\Scenario\Services\ScenarioVersionService;
use Tests\TestCase;

final class ScenarioVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ScenarioVersionService::class);
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    /**
     * Создание версии: запись появляется в БД, payload содержит id и status.
     */
    public function test_create_persists_version_and_returns_payload(): void
    {
        $scenario = $this->makeScenario();

        $result = $this->service->create($this->makeVersionData(name: 'v1'), $scenario);

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('v1', $result['name']);
        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'name' => 'v1',
        ]);
    }

    /**
     * Создание без имени — имя генерируется автоматически (v1, v2, …).
     */
    public function test_create_auto_generates_name_when_not_provided(): void
    {
        $scenario = $this->makeScenario();

        $this->service->create($this->makeVersionData(name: null), $scenario);
        $result = $this->service->create($this->makeVersionData(name: null), $scenario);

        $this->assertSame('v2', $result['name']);
    }

    /**
     * Создание сохраняет ревизию с переданным schema_json.
     */
    public function test_create_stores_revision_with_schema_json(): void
    {
        $scenario = $this->makeScenario();
        $schema = ['nodes' => [['id' => 'n1', 'type' => 'start', 'data' => [], 'position' => ['x' => 0, 'y' => 0]]]];

        $this->service->create($this->makeVersionData(schemaJson: $schema), $scenario);

        $version = ScenarioVersion::query()->where('scenario_id', $scenario->id)->firstOrFail();
        $revision = ScenarioVersionRevision::query()->where('scenario_version_id', $version->id)->firstOrFail();
        $this->assertNotEmpty($revision->nodes_json);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    /**
     * Обновление статуса сохраняется в БД.
     */
    public function test_update_persists_new_status(): void
    {
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario);

        $this->service->update($this->makeVersionData(status: 'active'), $version);

        $this->assertDatabaseHas('scenario_versions', ['id' => $version->id, 'status' => 'active']);
    }

    /**
     * Обновление добавляет новую ревизию (старая не удаляется).
     */
    public function test_update_appends_new_revision(): void
    {
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario);

        $this->service->update($this->makeVersionData(), $version);

        $count = ScenarioVersionRevision::query()->where('scenario_version_id', $version->id)->count();
        $this->assertSame(2, $count);
    }

    // -------------------------------------------------------------------------
    // duplicate
    // -------------------------------------------------------------------------

    /**
     * Дублирование создаёт копию со статусом draft и суффиксом «(копия)».
     */
    public function test_duplicate_creates_draft_copy_with_suffix(): void
    {
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, name: 'v1');

        $result = $this->service->duplicate(new ScenarioVersionActionData(), $version);

        $this->assertSame('v1 (копия)', $result['name']);
        $this->assertSame('draft', $result['status']);
        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'name' => 'v1 (копия)',
            'status' => 'draft',
        ]);
    }

    /**
     * Дублирование версии без ревизий не бросает исключений.
     */
    public function test_duplicate_works_when_version_has_no_revision(): void
    {
        $scenario = $this->makeScenario();
        $version = ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $scenario->id,
            'name' => 'v1',
            'status' => 'draft',
        ]);

        $result = $this->service->duplicate(new ScenarioVersionActionData(), $version);

        $this->assertSame('v1 (копия)', $result['name']);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    /**
     * Удаление версии — запись исчезает из БД.
     */
    public function test_delete_removes_version_from_db(): void
    {
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario);

        $this->service->delete(new ScenarioVersionActionData(), $version);

        $this->assertDatabaseMissing('scenario_versions', ['id' => $version->id]);
    }

    /**
     * Удаление версии каскадно удаляет её ревизии.
     */
    public function test_delete_cascades_to_revisions(): void
    {
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario);
        $revisionId = ScenarioVersionRevision::query()
            ->where('scenario_version_id', $version->id)
            ->value('id');

        $this->service->delete(new ScenarioVersionActionData(), $version);

        $this->assertDatabaseMissing('scenario_version_revisions', ['id' => $revisionId]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeScenario(): Scenario
    {
        return Scenario::query()->create(['name' => 'Scenario '.Str::random(4), 'is_active' => true]);
    }

    private function makeVersion(Scenario $scenario, string $name = 'v1'): ScenarioVersion
    {
        $version = ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $scenario->id,
            'name' => $name,
            'status' => 'draft',
        ]);

        ScenarioVersionRevision::query()->create([
            'scenario_version_id' => $version->id,
            'schema_json' => ['nodes' => [], 'edges' => []],
            'nodes_json' => [],
            'edges_json' => [],
            'schema_version' => 1,
        ]);

        return $version;
    }

    /**
     * @param  array<mixed>  $schemaJson
     */
    private function makeVersionData(
        ?string $name = 'v1',
        ?string $status = null,
        array $schemaJson = [],
    ): ScenarioVersionData {
        $schema = $schemaJson ?: ['nodes' => [], 'edges' => []];

        return new ScenarioVersionData(
            schemaJson: $schema,
            nodesJson: $schema['nodes'] ?? [],
            edgesJson: $schema['edges'] ?? [],
            schemaVersion: 1,
            name: $name,
            hasName: $name !== null,
            status: $status,
            hasStatus: $status !== null,
        );
    }
}
