<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ResourceConflictException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Module\Projects\CurrentProject;
use Module\Scenario\DTO\ScenarioFieldPresetData;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Models\ScenarioFieldPreset;
use Module\Scenario\Repositories\ScenarioFieldPresetRepository;
use Module\Users\Models\User;

final readonly class ScenarioFieldPresetService
{
    public function __construct(
        private ScenarioFieldPresetRepository $presets,
        private CurrentProject $currentProject,
    ) {
    }

    /** @return Collection<int, ScenarioFieldPreset> */
    public function all(): Collection
    {
        return $this->presets->allForProject($this->projectId());
    }

    public function create(ScenarioFieldPresetData $data, ?User $actor): ScenarioFieldPreset
    {
        try {
            return $this->presets->create([
                'project_id' => $this->projectId(),
                'name' => $data->name,
                'field_type' => $data->fieldType,
                'field' => $this->snapshot($data),
                'schema_version' => 1,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ResourceConflictException::from(ScenarioErrorCode::FieldPresetNameConflict);
        }
    }

    public function update(
        ScenarioFieldPresetData $data,
        ScenarioFieldPreset $preset,
        ?User $actor,
    ): ScenarioFieldPreset {
        $this->assertInCurrentProject($preset);

        try {
            return $this->presets->update($preset, [
                'name' => $data->name,
                'field_type' => $data->fieldType,
                'field' => $this->snapshot($data),
                'updated_by' => $actor?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ResourceConflictException::from(ScenarioErrorCode::FieldPresetNameConflict);
        }
    }

    public function delete(ScenarioFieldPreset $preset): void
    {
        $this->assertInCurrentProject($preset);
        $this->presets->delete($preset);
    }

    /** @return array<string, mixed> */
    private function snapshot(ScenarioFieldPresetData $data): array
    {
        $field = $data->field;
        unset($field['id']);
        $field['type'] = $data->fieldType->value;

        return $field;
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Scenario field preset operations require a current project.');
    }

    private function assertInCurrentProject(ScenarioFieldPreset $preset): void
    {
        if ($preset->project_id !== $this->projectId()) {
            throw NotFoundException::from(ScenarioErrorCode::FieldPresetNotFound);
        }
    }
}
