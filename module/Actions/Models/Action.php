<?php

declare(strict_types=1);

namespace Module\Actions\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @property string                                $id
 * @property string                                $name
 * @property string                                $slug
 * @property string                                $code
 * @property string|null                           $description
 * @property string                                $type
 * @property bool                                  $is_active
 * @property array<string, mixed>|null             $config
 * @property array<string, mixed>|null             $schema
 * @property array<string, mixed>|null             $ui_schema
 * @property array<int, array<string, mixed>>|null $input_fields
 * @property list<int>|null                        $default_backoff
 * @property Carbon|null                           $created_at
 * @property Carbon|null                           $updated_at
 * @property-read Collection<int, ActionRun> $runs
 * @property-read ActionSchedule|null $schedule
 * @property-read Collection<int, Category> $categories
 */
final class Action extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'type',
        'is_active',
        'config',
        'schema',
        'ui_schema',
        'input_fields',
        'default_backoff',
    ];

    /** @return HasMany<ActionRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ActionRun::class)->latest();
    }

    /** @return HasOne<ActionSchedule, $this> */
    public function schedule(): HasOne
    {
        return $this->hasOne(ActionSchedule::class);
    }

    /** @return MorphToMany<Category, $this> */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'model', 'model_has_categories', 'model_id', 'category_id')
            ->withPivot('project_id')
            ->withTimestamps();
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'schema' => 'array',
            'ui_schema' => 'array',
            'input_fields' => 'array',
            'default_backoff' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
