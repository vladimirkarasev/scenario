<?php

declare(strict_types=1);

namespace Module\Actions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int                       $id
 * @property string                    $action_id
 * @property bool                      $enabled
 * @property string|null               $cron
 * @property string                    $timezone
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $settings
 * @property Carbon|null               $last_run_at
 * @property Carbon|null               $next_run_at
 */
final class ActionSchedule extends Model
{
    protected $fillable = [
        'action_id',
        'enabled',
        'cron',
        'timezone',
        'input',
        'options',
        'settings',
        'last_run_at',
        'next_run_at',
    ];

    /** @return BelongsTo<Action, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'input' => 'array',
            'options' => 'array',
            'settings' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
