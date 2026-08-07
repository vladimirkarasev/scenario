<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Module\Projects\Models\Project;
use Module\Scenario\Enums\BlockFieldType;
use Module\Users\Models\User;

/**
 * @property string $id
 * @property string $project_id
 * @property string $name
 * @property BlockFieldType $field_type
 * @property array<string, mixed> $field
 * @property int $schema_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable('project_id', 'name', 'field_type', 'field', 'schema_version', 'created_by', 'updated_by')]
final class ScenarioFieldPreset extends Model
{
    use HasUuids;

    /** @return BelongsTo<Project, ScenarioFieldPreset> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, ScenarioFieldPreset> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, ScenarioFieldPreset> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'field_type' => BlockFieldType::class,
            'field' => 'array',
            'schema_version' => 'integer',
        ];
    }
}
