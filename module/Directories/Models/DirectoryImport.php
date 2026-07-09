<?php

declare(strict_types=1);

namespace Module\Directories\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Module\Users\Models\User;

/**
 * @property int $id
 * @property string $directory_id
 * @property array<string, string> $mapping_json
 * @property array<int, array<string, mixed>> $fields_json
 * @property array<string, mixed> $remote_config_json
 * @property array<int, string> $processed_keys_json
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable(
    'directory_id',
    'directory_version_id',
    'uploaded_by',
    'mode',
    'status',
    'source_type',
    'file_disk',
    'file_path',
    'match_by',
    'parent_key_field',
    'chunk_size',
    'mapping_json',
    'fields_json',
    'remote_config_json',
    'processed_keys_json',
    'processed_rows',
    'imported_rows',
    'failed_rows',
    'error_message',
    'started_at',
    'finished_at',
)]
final class DirectoryImport extends Model
{

    /** @return BelongsTo<Directory, $this> */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(Directory::class);
    }

    /** @return BelongsTo<DirectoryVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DirectoryVersion::class, 'directory_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'mapping_json' => 'array',
            'fields_json' => 'array',
            'remote_config_json' => 'array',
            'processed_keys_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
