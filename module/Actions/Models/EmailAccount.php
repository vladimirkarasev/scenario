<?php

declare(strict_types=1);

namespace Module\Actions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Module\Projects\Models\Project;

/**
 * @property string                    $id
 * @property string|null               $project_id
 * @property string                    $from_address
 * @property string|null               $from_name
 * @property string                    $driver
 * @property array<string, mixed>|null $settings
 * @property bool                      $is_active
 */
final class EmailAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'from_address',
        'from_name',
        'driver',
        'settings',
        'is_active',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
