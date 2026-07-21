<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use Module\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\QueryBuilders\ScenarioRunBuilder;

/**
 * @property string $id
 * @property int|null $number
 * @property string $scenario_id
 * @property string|null $scenario_version_id
 * @property string $scenario_version_revision_id
 * @property string|null $current_node_id
 * @property array<string, mixed>|null $context
 * @property ScenarioRunStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $operator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Scenario|null $scenario
 * @property-read ScenarioVersion|null $version
 * @property-read ScenarioVersionRevision|null $revision
 * @property-read Collection<int, ScenarioRunStep> $steps
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read User|null $operator
 *
 * @method static ScenarioRunBuilder query()
 */
#[UseEloquentBuilder(ScenarioRunBuilder::class)]
#[Fillable('id', 'scenario_id', 'scenario_version_id', 'scenario_version_revision_id', 'current_node_id', 'context', 'status', 'created_by', 'updated_by', 'operator_id')]
final class ScenarioRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<Scenario, ScenarioRun> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /** @return BelongsTo<ScenarioVersion, ScenarioRun> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'scenario_version_id');
    }

    /** @return BelongsTo<ScenarioVersionRevision, ScenarioRun> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersionRevision::class, 'scenario_version_revision_id');
    }

    /** @return HasMany<ScenarioRunStep, ScenarioRun> */
    public function steps(): HasMany
    {
        return $this->hasMany(ScenarioRunStep::class, 'run_id')
            ->whereNull('cancelled_at')
            ->latest('id');
    }

    /**
     * @return HasMany<ScenarioRunStep, ScenarioRun>
     */
    public function stepHistory(): HasMany
    {
        return $this->hasMany(ScenarioRunStep::class, 'run_id')->orderBy('id');
    }

    /** @return BelongsTo<User, ScenarioRun> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, ScenarioRun> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, ScenarioRun> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function formattedNumber(int $width = 7): string
    {
        return str_pad((string)$this->number, $width, '0', STR_PAD_LEFT);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'context' => 'array',
            'status' => ScenarioRunStatus::class,
        ];
    }

    #[\Override]
    protected static function booted(): void
    {
        self::creating(static function (ScenarioRun $run): void {
            $run->ensureUuidKey();
            $run->fillAuditUsers();
            $run->fillNumberWithoutSequence();
        });

        self::created(static function (ScenarioRun $run): void {
            $run->refreshGeneratedNumber();
        });
    }

    private function ensureUuidKey(): void
    {
        if (!$this->getKey()) {
            $this->{$this->getKeyName()} = (string)Str::uuid();
        }
    }

    private function fillAuditUsers(): void
    {
        $userId = Auth::id();
        if ($userId !== null) {
            $this->created_by ??= (int)$userId;
            $this->updated_by ??= (int)$userId;
            $this->operator_id ??= (int)$userId;
        }
    }

    private function fillNumberWithoutSequence(): void
    {
        if ($this->number === null && $this->getConnection()->getDriverName() !== 'pgsql') {
            $maxNumber = self::query()->max('number');
            $this->number = (is_numeric($maxNumber) ? (int)$maxNumber : 0) + 1;
        }
    }

    private function refreshGeneratedNumber(): void
    {
        if ($this->number === null) {
            $this->refresh();
        }
    }
}
