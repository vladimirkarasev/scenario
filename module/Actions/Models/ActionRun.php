<?php

declare(strict_types=1);

namespace Module\Actions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int                       $id
 * @property string                    $action_id
 * @property string                    $status
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property string|null               $error
 * @property int                       $attempts_count
 * @property Carbon|null               $started_at
 * @property Carbon|null               $finished_at
 * @property int|null                  $duration_ms
 * @property Carbon|null               $created_at
 * @property Carbon|null               $updated_at
 */
final class ActionRun extends Model
{
    protected $fillable = [
        'action_id',
        'status',
        'input',
        'output',
        'error',
        'attempts_count',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    /** @return BelongsTo<Action, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
