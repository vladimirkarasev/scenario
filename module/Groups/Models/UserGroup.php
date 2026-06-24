<?php

declare(strict_types=1);

namespace Module\Groups\Models;

use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Module\Groups\QueryBuilders\UserGroupBuilder;
use Module\Scenario\Models\Scenario;

/**
 * @property string $id
 * @property string|null $site_id
 * @property string $name
 * @property string $slug
 * @property string|null $ext_id
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $members_count
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Scenario> $scenarios
 *
 * @method static UserGroupBuilder query()
 */
final class UserGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'ext_id',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @return BelongsToMany<User, UserGroup> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_members', 'user_group_id', 'user_id')
            ->withTimestamps();
    }

    /** @return MorphToMany<Category, UserGroup> */
    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'model', 'model_has_groups', 'group_id', 'model_id')
            ->withTimestamps();
    }

    /** @return MorphToMany<Scenario, UserGroup> */
    public function scenarios(): MorphToMany
    {
        return $this->morphedByMany(Scenario::class, 'model', 'model_has_groups', 'group_id', 'model_id')
            ->withTimestamps();
    }

    /** @return BelongsTo<User, UserGroup> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, UserGroup> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    #[\Override]
    public function newEloquentBuilder($query): UserGroupBuilder
    {
        return new UserGroupBuilder($query);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
