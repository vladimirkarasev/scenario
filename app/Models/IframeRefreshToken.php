<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Module\Projects\Models\Project;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $project_id
 * @property int|null    $personal_access_token_id
 * @property string      $token_hash
 * @property Carbon      $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User    $user
 * @property-read Project $project
 */
final class IframeRefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'personal_access_token_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
