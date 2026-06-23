<?php

declare(strict_types=1);

namespace Module\Directories\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $directory_id
 * @property int $version_number
 * @property string|null $code
 * @property string|null $status
 * @property bool $is_active
 * @property string $source_type
 * @property array{add_new: bool, update_existing: bool, delete_unused: bool}|null $sync_options
 * @property bool $allow_other
 * @property string|null $other_label
 * @property string|null $other_external_key
 * @property array<int, array<string, mixed>> $schema_json
 * @property int|null $source_import_id
 * @property array<string, mixed>|null $source_metadata_json
 * @property-read int|null $items_count
 * @property-read int|null $imports_count
 */
final class DirectoryVersion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'directory_id',
        'version_number',
        'code',
        'status',
        'is_active',
        'source_type',
        'sync_options',
        'allow_other',
        'other_label',
        'other_external_key',
        'schema_json',
        'source_import_id',
        'source_metadata_json',
    ];

    /** @return BelongsTo<Directory, $this> */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(Directory::class);
    }

    /** @return HasMany<DirectoryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DirectoryItem::class);
    }

    /** @return HasMany<DirectoryImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(DirectoryImport::class);
    }

    /** @return BelongsTo<DirectoryImport, $this> */
    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(DirectoryImport::class, 'source_import_id');
    }

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'sync_options' => 'array',
            'source_metadata_json' => 'array',
            'is_active' => 'boolean',
            'allow_other' => 'boolean',
        ];
    }
}
