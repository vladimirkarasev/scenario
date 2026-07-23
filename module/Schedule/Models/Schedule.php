<?php

declare(strict_types=1);

namespace Module\Schedule\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int          $id
 * @property string        $scope
 * @property string        $subject_id
 * @property bool          $enabled
 * @property string|null   $cron
 * @property string        $timezone
 * @property string        $workflow_type
 * @property string        $task_queue
 * @property list<mixed>|null $workflow_input
 * @property Carbon|null   $last_run_at
 * @property Carbon|null   $next_run_at
 */
#[Fillable(
    'scope',
    'subject_id',
    'enabled',
    'cron',
    'timezone',
    'workflow_type',
    'task_queue',
    'workflow_input',
    'last_run_at',
    'next_run_at',
)]
final class Schedule extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'workflow_input' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
