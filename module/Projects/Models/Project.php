<?php

declare(strict_types=1);

namespace Module\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string      $id
 * @property string      $name
 * @property string|null $sitekey
 * @property string|null $host
 * @property string|null $shared_secret
 * @property bool        $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Project extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'sitekey',
        'host',
        'shared_secret',
        'is_active',
    ];

    protected $hidden = [
        'shared_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
