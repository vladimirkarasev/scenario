<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Directories\Http\Requests\DirectorySyncScheduleRequest;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;
use Module\Directories\Services\DirectorySyncScheduleService;

final class DirectorySyncScheduleController extends Controller
{
    public function __construct(
        private readonly DirectorySyncScheduleService $service,
        private readonly DirectoryService $directoryService,
    ) {}

    public function show(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        return new JsonResponse(['data' => $this->service->payload($this->service->findForDirectory($directory))]);
    }

    public function upsert(DirectorySyncScheduleRequest $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $validated = $request->validated();

        $schedule = $this->service->upsert(
            directory: $directory,
            enabled: (bool)$validated['enabled'],
            cron: is_string($validated['cron'] ?? null) && $validated['cron'] !== '' ? $validated['cron'] : null,
            timezone: is_string($validated['timezone'] ?? null) ? $validated['timezone'] : null,
        );

        return new JsonResponse(['data' => $this->service->payload($schedule)]);
    }

    public function destroy(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $this->service->disable($directory);

        return new JsonResponse(null, 204);
    }
}
