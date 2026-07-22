<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Enums\ScenarioRunHistoryEventType;
use Module\Users\Models\User;

/**
 * @property int $id
 * @property string $run_id
 * @property int|null $step_id
 * @property string|null $scenario_version_id
 * @property string|null $scenario_version_revision_id
 * @property string|null $node_id
 * @property ScenarioNodeType|null $node_type
 * @property ScenarioRunHistoryEventType $type
 * @property int|null $actor_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 * @property-read ScenarioRun|null $run
 * @property-read ScenarioRunStep|null $step
 * @property-read User|null $actor
 */
#[Fillable('run_id', 'step_id', 'scenario_version_id', 'scenario_version_revision_id', 'node_id', 'node_type', 'type', 'actor_id', 'payload', 'occurred_at')]
final class ScenarioRunHistoryEvent extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<ScenarioRun, ScenarioRunHistoryEvent> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ScenarioRun::class, 'run_id');
    }

    /** @return BelongsTo<ScenarioRunStep, ScenarioRunHistoryEvent> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ScenarioRunStep::class, 'step_id');
    }

    /** @return BelongsTo<User, ScenarioRunHistoryEvent> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'node_type' => ScenarioNodeType::class,
            'type' => ScenarioRunHistoryEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
