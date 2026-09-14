<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario;

use App\Exceptions\NotFoundException;
use App\Exceptions\ResourceConflictException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\CurrentProject;
use Module\Projects\Models\Project;
use Module\Scenario\DTO\ScenarioFieldPresetData;
use Module\Scenario\Enums\BlockFieldType;
use Module\Scenario\Models\ScenarioFieldPreset;
use Module\Scenario\Services\Definition\ScenarioFieldPresetService;
use Tests\TestCase;

final class ScenarioFieldPresetServiceTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private ScenarioFieldPresetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->app->instance(CurrentProject::class, new CurrentProject($this->project));
        $this->service = app(ScenarioFieldPresetService::class);
    }

    public function test_create_scopes_preset_and_removes_field_identity(): void
    {
        $preset = $this->service->create($this->data('ФИО'), null);

        self::assertSame($this->project->id, $preset->project_id);
        self::assertSame(BlockFieldType::Input, $preset->field_type);
        self::assertArrayNotHasKey('id', $preset->field);
        self::assertSame('client_name', $preset->field['varName']);
    }

    public function test_all_returns_only_current_project_presets(): void
    {
        $own = $this->service->create($this->data('Свое поле'), null);
        $otherProject = $this->makeProject();
        ScenarioFieldPreset::query()->create([
            'project_id' => $otherProject->id,
            'name' => 'Чужое поле',
            'field_type' => BlockFieldType::Input,
            'field' => ['type' => 'input'],
        ]);

        self::assertSame([$own->id], $this->service->all()->modelKeys());
    }

    public function test_update_replaces_name_type_and_snapshot(): void
    {
        $preset = $this->service->create($this->data('ФИО'), null);
        $data = new ScenarioFieldPresetData(
            name: 'Город',
            fieldType: BlockFieldType::Select,
            field: [
                'id' => 'runtime-id',
                'type' => 'select',
                'label' => 'Город',
                'varName' => 'city',
                'options' => [['id' => 'moscow', 'label' => 'Москва', 'value' => 'msk', 'parentId' => null]],
            ],
        );

        $updated = $this->service->update($data, $preset, null);

        self::assertSame('Город', $updated->name);
        self::assertSame(BlockFieldType::Select, $updated->field_type);
        self::assertArrayNotHasKey('id', $updated->field);
        self::assertSame('Москва', $updated->field['options'][0]['label']);
    }

    public function test_update_rejects_preset_from_another_project(): void
    {
        $foreign = ScenarioFieldPreset::query()->create([
            'project_id' => $this->makeProject()->id,
            'name' => 'Чужое поле',
            'field_type' => BlockFieldType::Input,
            'field' => ['type' => 'input'],
        ]);

        try {
            $this->service->update($this->data('Новое имя'), $foreign, null);
            self::fail('Expected NotFoundException.');
        } catch (NotFoundException $exception) {
            self::assertSame('SCENARIO_FIELD_PRESET_NOT_FOUND', $exception->errorCode);
        }
    }

    public function test_delete_removes_current_project_preset(): void
    {
        $preset = $this->service->create($this->data('Удалить'), null);

        $this->service->delete($preset);

        self::assertDatabaseMissing('scenario_field_presets', ['id' => $preset->id]);
    }

    public function test_create_converts_unique_name_race_to_domain_conflict(): void
    {
        $this->service->create($this->data('ФИО'), null);

        try {
            $this->service->create($this->data('ФИО'), null);
            self::fail('Expected ResourceConflictException.');
        } catch (ResourceConflictException $exception) {
            self::assertSame(409, $exception->status());
            self::assertSame('SCENARIO_FIELD_PRESET_NAME_CONFLICT', $exception->errorCode);
        }
    }

    private function data(string $name): ScenarioFieldPresetData
    {
        return new ScenarioFieldPresetData(
            name: $name,
            fieldType: BlockFieldType::Input,
            field: [
                'id' => 'runtime-id',
                'type' => 'input',
                'label' => 'ФИО',
                'varName' => 'client_name',
                'placeholder' => 'Иванов Иван',
            ],
        );
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(8),
            'host' => Str::random(8).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
    }
}
