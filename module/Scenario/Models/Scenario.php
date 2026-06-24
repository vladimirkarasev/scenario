<?php

declare(strict_types=1);

namespace Module\Scenario\Models;

use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\QueryBuilders\ScenarioBuilder;

/**
 * @property string $id
 * @property string|null $project_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property ScenarioStatus $status
 * @property string|null $alias
 * @property array<int, string>|null $tags
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string|null $active_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read Collection<int, ScenarioVersion> $versions
 * @property-read Collection<int, ScenarioRun> $runs
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, UserGroup> $groups
 *
 * @method static ScenarioBuilder query()
 */
final class Scenario extends Model
{
    use HasUuids;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'is_active',
        'status',
        'alias',
        'tags',
        'created_by',
        'updated_by',
        'active_version_id',
    ];

    /** @return BelongsTo<Project, Scenario> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return MorphToMany<Category, Scenario> */
    public function categories(): MorphToMany
    {
        return $this
            ->morphToMany(Category::class, 'model', 'model_has_categories', 'model_id', 'category_id')
            ->withPivot('project_id')
            ->withTimestamps();
    }

    /** @return MorphToMany<UserGroup, Scenario> */
    public function groups(): MorphToMany
    {
        return $this
            ->morphToMany(UserGroup::class, 'model', 'model_has_groups', 'model_id', 'group_id')
            ->withTimestamps();
    }

    /** @return HasMany<ScenarioVersion, Scenario> */
    public function versions(): HasMany
    {
        return $this
            ->hasMany(ScenarioVersion::class)
            ->orderByDesc('created_at');
    }

    /** @return HasMany<ScenarioVersion, Scenario> */
    public function latestVersion(): HasMany
    {
        return $this->versions()->limit(1);
    }

    /** @return BelongsTo<ScenarioVersion, Scenario> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'active_version_id');
    }

    /** @return HasMany<ScenarioRun, Scenario> */
    public function runs(): HasMany
    {
        return $this
            ->hasMany(ScenarioRun::class)
            ->latest();
    }

    /** @return BelongsTo<User, Scenario> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, Scenario> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    #[\Override]
    public function newEloquentBuilder($query): ScenarioBuilder
    {
        return new ScenarioBuilder($query);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => ScenarioStatus::class,
            'tags' => 'array',
        ];
    }
}
