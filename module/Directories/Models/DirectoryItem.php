<?php

declare(strict_types=1);

namespace Module\Directories\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $directory_version_id
 * @property int|null $parent_id
 * @property string|null $external_key
 * @property string|null $search_text
 * @property array<string, mixed>|null $data_json
 */
final class DirectoryItem extends Model
{
    protected $fillable = [
        'directory_version_id',
        'parent_id',
        'external_key',
        'search_text',
        'data_json',
    ];

    /** @return BelongsTo<DirectoryVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DirectoryVersion::class, 'directory_version_id');
    }

    /** @return BelongsTo<DirectoryItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DirectoryItem::class, 'parent_id');
    }

    /** @return HasMany<DirectoryItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(DirectoryItem::class, 'parent_id');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'data_json' => 'array',
        ];
    }
}
