<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Actions\Models\ActionSchedule;

final class ActionScheduleListController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $items = ActionSchedule::query()
            ->with('action')
            ->orderByDesc('enabled')
            ->orderBy('next_run_at')
            ->get()
            ->map(static fn(ActionSchedule $schedule): array => [
                'id' => $schedule->id,
                'enabled' => $schedule->enabled,
                'cron' => $schedule->cron,
                'timezone' => $schedule->timezone,
                'last_run_at' => $schedule->last_run_at?->toIso8601String(),
                'next_run_at' => $schedule->next_run_at?->toIso8601String(),
                'action' => $schedule->action ? [
                    'id' => $schedule->action->id,
                    'name' => $schedule->action->name,
                    'code' => $schedule->action->code,
                    'type' => $schedule->action->type,
                    'is_active' => $schedule->action->is_active,
                ] : null,
            ])
            ->all();

        return new JsonResponse(['items' => $items, 'meta' => ['total' => count($items)]]);
    }
}
