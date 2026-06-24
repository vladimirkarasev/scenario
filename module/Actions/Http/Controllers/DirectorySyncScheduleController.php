<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Actions\Http\Requests\DirectorySyncScheduleRequest;
use Module\Actions\Models\ActionSchedule;
use Module\Actions\Services\DirectorySyncScheduleService;
use Module\Directories\Models\Directory;

final class DirectorySyncScheduleController extends Controller
{
    public function __construct(
        private readonly DirectorySyncScheduleService $service,
    ) {}

    public function show(Directory $directory): JsonResponse
    {
        return new JsonResponse(['data' => $this->payload($this->service->findSchedule($directory))]);
    }

    public function upsert(DirectorySyncScheduleRequest $request, Directory $directory): JsonResponse
    {
        $validated = $request->validated();

        $schedule = $this->service->upsert(
            directory: $directory,
            enabled: (bool) $validated['enabled'],
            cron: is_string($validated['cron'] ?? null) && $validated['cron'] !== '' ? $validated['cron'] : null,
            timezone: is_string($validated['timezone'] ?? null) ? $validated['timezone'] : null,
        );

        return new JsonResponse(['data' => $this->payload($schedule)]);
    }

    public function destroy(Directory $directory): JsonResponse
    {
        $this->service->disable($directory);

        return new JsonResponse(null, 204);
    }

    /** @return array<string, mixed>|null */
    private function payload(?ActionSchedule $schedule): ?array
    {
        if (! $schedule instanceof ActionSchedule) {
            return null;
        }

        return [
            'enabled' => $schedule->enabled,
            'cron' => $schedule->cron,
            'timezone' => $schedule->timezone,
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ];
    }
}
