<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $scenario_version_id
 * @property array<string, mixed>|null $schema_json
 * @property array<int, array<string, mixed>>|null $nodes_json
 * @property array<int, array<string, mixed>>|null $edges_json
 * @property array<int, array<string, mixed>>|null $input_fields
 * @property int|null $schema_version
 * @property Carbon|null $created_at
 * @property-read ScenarioVersion|null $version
 */
final class ScenarioVersionRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'scenario_version_id',
        'schema_json',
        'nodes_json',
        'edges_json',
        'input_fields',
        'schema_version',
        'created_at',
    ];

    /** @return BelongsTo<ScenarioVersion, ScenarioVersionRevision> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'nodes_json' => 'array',
            'edges_json' => 'array',
            'input_fields' => 'array',
            'schema_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
