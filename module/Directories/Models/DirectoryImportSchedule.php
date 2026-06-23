<?php

declare(strict_types=1);

namespace Module\Directories\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, string>|null $mapping_json
 * @property array<int, array<string, mixed>>|null $fields_json
 * @property array<string, mixed>|null $remote_config_json
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 */
final class DirectoryImportSchedule extends Model
{
    protected $fillable = [
        'directory_id',
        'enabled',
        'frequency',
        'run_at',
        'timezone',
        'mode',
        'match_by',
        'chunk_size',
        'mapping_json',
        'fields_json',
        'remote_config_json',
        'last_run_at',
        'next_run_at',
    ];

    /** @return BelongsTo<Directory, $this> */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(Directory::class);
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mapping_json' => 'array',
            'fields_json' => 'array',
            'remote_config_json' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
