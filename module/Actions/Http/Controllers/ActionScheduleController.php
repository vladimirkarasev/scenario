<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Actions\Http\Requests\ActionScheduleRequest;
use Module\Actions\Http\Resources\JsonApi\ActionScheduleResource;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Actions\Services\ActionScheduleService;

final class ActionScheduleController extends Controller
{
    public function __construct(private readonly ActionScheduleService $schedules) {}

    public function show(Action $action): ActionScheduleResource|JsonResponse
    {
        $schedule = $action->schedule()->first();

        if (! $schedule instanceof ActionSchedule) {
            return new JsonResponse(['data' => null]);
        }

        return new ActionScheduleResource($schedule);
    }

    public function upsert(ActionScheduleRequest $request, Action $action): ActionScheduleResource
    {
        $validated = $request->validated();

        $schedule = $this->schedules->upsert(
            action: $action,
            enabled: (bool) $validated['enabled'],
            cron: is_string($validated['cron'] ?? null) && $validated['cron'] !== '' ? $validated['cron'] : null,
            timezone: is_string($validated['timezone'] ?? null) ? $validated['timezone'] : null,
            input: $this->stringKeyedArray($validated['input'] ?? null),
            options: $this->stringKeyedArray($validated['options'] ?? null),
            settings: $this->stringKeyedArray($validated['settings'] ?? null),
        );

        return new ActionScheduleResource($schedule);
    }

    public function destroy(Request $request, Action $action): JsonResponse
    {
        abort_unless($request->user() !== null, 403);

        $schedule = $action->schedule()->first();

        if ($schedule instanceof ActionSchedule) {
            $this->schedules->delete($schedule);
        }

        return new JsonResponse(null, 204);
    }

    /** @return array<string, mixed> */
    private function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }
}
