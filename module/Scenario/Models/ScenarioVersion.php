<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;

/**
 * @property string      $id
 * @property string      $scenario_id
 * @property string|null $project_id
 * @property string|null $name
 * @property string      $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 * @property-read Scenario|null $scenario
 * @property-read Collection<int, ScenarioRun> $runs
 * @property-read Collection<int, ScenarioVersionRevision> $revisions
 * @property-read ScenarioVersionRevision|null $latestRevision
 */
final class ScenarioVersion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'scenario_id',
        'project_id',
        'name',
        'status',
        'created_at',
    ];

    /** @return BelongsTo<Project, ScenarioVersion> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Scenario, ScenarioVersion> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /** @return HasMany<ScenarioRun, ScenarioVersion> */
    public function runs(): HasMany
    {
        return $this->hasMany(ScenarioRun::class, 'scenario_version_id');
    }

    /** @return HasMany<ScenarioVersionRevision, ScenarioVersion> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ScenarioVersionRevision::class, 'scenario_version_id')
            ->orderByDesc('created_at');
    }

    /** @return HasOne<ScenarioVersionRevision, ScenarioVersion> */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(ScenarioVersionRevision::class, 'scenario_version_id')
            ->orderByDesc('created_at');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(static function (ScenarioVersion $version): void {
            if (! $version->getKey()) {
                $version->{$version->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
