<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Module\Directories\Http\Requests\UpsertDirectoryImportScheduleRequest;
use Module\Directories\Http\Resources\JsonApi\DirectoryImportScheduleResource;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryImportScheduleService;
use Module\Directories\Services\DirectoryService;

final class DirectoryImportScheduleController extends Controller
{
    public function __construct(
        private readonly DirectoryImportScheduleService $scheduleService,
        private readonly DirectoryService $directoryService,
    ) {}

    public function upsert(UpsertDirectoryImportScheduleRequest $request, Directory $directory): DirectoryImportScheduleResource
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array<string, string> $mapping */
        $mapping = is_array($validated['mapping'] ?? null) ? $validated['mapping'] : [];
        /** @var array<int, array<string, mixed>> $fields */
        $fields = is_array($validated['fields'] ?? null) ? $validated['fields'] : [];
        /** @var array<string, mixed> $remote */
        $remote = is_array($validated['remote'] ?? null) ? $validated['remote'] : [];

        $schedule = $this->scheduleService->upsert(
            directory: $directory,
            enabled: (bool) ($validated['enabled'] ?? false),
            frequency: is_string($validated['frequency'] ?? null) ? $validated['frequency'] : 'daily',
            runAt: is_string($validated['run_at'] ?? null) ? $validated['run_at'] : '00:00',
            timezone: is_string($validated['timezone'] ?? null) ? $validated['timezone'] : null,
            mode: is_string($validated['mode'] ?? null) ? $validated['mode'] : '',
            mapping: $mapping,
            fields: $fields,
            remote: $remote,
            matchBy: is_string($validated['match_by'] ?? null) ? $validated['match_by'] : null,
            chunkSize: is_int($validated['chunk_size'] ?? null) ? $validated['chunk_size'] : 500,
        );

        /** @var array<string, mixed> $payload */
        $payload = $this->scheduleService->payload($schedule) ?? [];

        return new DirectoryImportScheduleResource($payload);
    }
}
