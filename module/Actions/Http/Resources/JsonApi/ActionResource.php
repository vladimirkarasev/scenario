<?php

declare(strict_types=1);

namespace Module\Actions\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;

/**
 * @mixin Action
 */
final class ActionResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'actions';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'key' => $this->key,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'config' => $this->config,
            'schema' => $this->schema,
            'ui_schema' => $this->ui_schema,
            'input_fields' => $this->input_fields ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'runs' => fn () => [
                'data' => $this->relationLoaded('runs')
                    ? $this->runs()
                    : [],
            ],
            'schedule' => fn () => [
                'data' => $this->relationLoaded('schedule') && $this->schedule instanceof ActionSchedule
                    ? $this->schedule()
                    : null,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function runs(): array
    {
        $runs = [];

        foreach ($this->runs as $run) {
            $runs[] = [
                'id' => $run->id,
                'status' => $run->status,
                'input' => $run->input,
                'output' => $run->output,
                'error' => $run->error,
                'attempts_count' => $run->attempts_count,
                'started_at' => $this->dateTime($run->started_at),
                'finished_at' => $this->dateTime($run->finished_at),
                'duration_ms' => $run->duration_ms,
            ];
        }

        return $runs;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }

    /** @return array<string, mixed> */
    private function schedule(): array
    {
        /** @var ActionSchedule $schedule */
        $schedule = $this->schedule;

        return [
            'id' => $schedule->id,
            'action_id' => $schedule->action_id,
            'enabled' => $schedule->enabled,
            'cron' => $schedule->cron,
            'timezone' => $schedule->timezone,
            'input' => $schedule->input,
            'options' => $schedule->options,
            'settings' => $schedule->settings,
            'last_run_at' => $this->dateTime($schedule->last_run_at),
            'next_run_at' => $this->dateTime($schedule->next_run_at),
        ];
    }
}
