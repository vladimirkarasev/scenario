<?php

declare(strict_types=1);

namespace App\Models;

use App\QueryBuilders\CategoryBuilder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Module\Actions\Models\Action;
use Module\Groups\Models\UserGroup;
use Module\Scenario\Models\Scenario;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $name
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $scenarios_count
 * @property-read int|null $children_count
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, Scenario> $scenarios
 * @property-read Collection<int, Action> $actions
 * @property-read Collection<int, UserGroup> $groups
 */
final class Category extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'parent_id',
        'name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @return BelongsTo<User, Category> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, Category> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return MorphToMany<Scenario, Category> */
    public function scenarios(): MorphToMany
    {
        return $this
            ->morphedByMany(Scenario::class, 'model', 'model_has_categories', 'category_id', 'model_id')
            ->withTimestamps();
    }

    /** @return MorphToMany<Action, Category> */
    public function actions(): MorphToMany
    {
        return $this
            ->morphedByMany(Action::class, 'model', 'model_has_categories', 'category_id', 'model_id')
            ->withTimestamps();
    }

    /** @return MorphToMany<UserGroup, Category> */
    public function groups(): MorphToMany
    {
        return $this
            ->morphToMany(UserGroup::class, 'model', 'model_has_groups', 'model_id', 'group_id')
            ->withTimestamps();
    }

    /** @return BelongsTo<Category, Category> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, Category> */
    public function children(): HasMany
    {
        return $this
            ->hasMany(self::class, 'parent_id')
            ->orderBy('name');
    }

    /** @return CategoryBuilder<static> */
    public function newEloquentBuilder($query): CategoryBuilder
    {
        return new CategoryBuilder($query);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
