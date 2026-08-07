<?php

declare(strict_types=1);

namespace Module\Directories\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Module\Directories\Enums\DirectorySourceType;
use Module\Projects\Models\Project;

/**
 * @property string $id
 * @property string|null $project_id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $source_type
 * @property string|null $match_by
 * @property string|null $default_sort
 * @property string|null $sync_status
 * @property string|null $sync_error
 * @property int|null $cache_ttl_seconds
 * @property array<string, mixed>|null $api_config_json
 * @property array<string, mixed>|null $import_settings_json
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $next_sync_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $versions_count
 */
#[Fillable(
    'project_id',
    'name',
    'slug',
    'description',
    'source_type',
    'match_by',
    'default_sort',
    'api_config_json',
    'import_settings_json',
    'last_sync_at',
    'next_sync_at',
    'sync_status',
    'sync_error',
    'cache_ttl_seconds',
)]
final class Directory extends Model
{
    use HasUuids;

    public function sourceType(): DirectorySourceType
    {
        return DirectorySourceType::tryFrom($this->source_type ?? '')
            ?? DirectorySourceType::Manual;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return MorphToMany<Category, $this> */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'model', 'model_has_categories', 'model_id', 'category_id')
            ->withPivot('project_id')
            ->withTimestamps();
    }

    /** @return HasMany<DirectoryVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DirectoryVersion::class)
            ->orderByDesc('version_number');
    }

    /** @return HasOne<DirectoryVersion, $this> */
    public function activeVersion(): HasOne
    {
        return $this->hasOne(DirectoryVersion::class)
            ->where('is_active', true);
    }

    /** @return HasOne<DirectoryVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DirectoryVersion::class)
            ->latestOfMany('version_number');
    }

    /** @return HasMany<DirectoryImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(DirectoryImport::class)
            ->latest();
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'api_config_json' => 'encrypted:array',
            'import_settings_json' => 'array',
            'last_sync_at' => 'datetime',
            'next_sync_at' => 'datetime',
        ];
    }
}
