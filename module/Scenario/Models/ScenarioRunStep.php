<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Module\Scenario\Enums\ScenarioNodeType;

/**
 * @property int $id
 * @property int $run_id
 * @property string $node_id
 * @property ScenarioNodeType $node_type
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property Carbon|null $entered_at
 * @property Carbon|null $exited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScenarioRun|null $run
 */
final class ScenarioRunStep extends Model
{
    protected $fillable = [
        'run_id',
        'node_id',
        'node_type',
        'input',
        'output',
        'entered_at',
        'exited_at',
    ];

    /** @return BelongsTo<ScenarioRun, ScenarioRunStep> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ScenarioRun::class, 'run_id');
    }

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'node_type' => ScenarioNodeType::class,
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }
}
