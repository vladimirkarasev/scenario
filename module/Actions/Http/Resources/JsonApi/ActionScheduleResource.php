<?php

declare(strict_types=1);

namespace Module\Actions\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Actions\Models\ActionSchedule;

/**
 * @mixin ActionSchedule
 */
final class ActionScheduleResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string)$this->id;
    }

    public function toType(Request $request): string
    {
        return 'action-schedules';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'action_id' => $this->action_id,
            'enabled' => $this->enabled,
            'cron' => $this->cron,
            'timezone' => $this->timezone,
            'input' => $this->input,
            'options' => $this->options,
            'settings' => $this->settings,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
