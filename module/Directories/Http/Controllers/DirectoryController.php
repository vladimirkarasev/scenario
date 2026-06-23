<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Directories\DTO\DirectoryData;
use Module\Directories\Http\Requests\DirectoryRequest;
use Module\Directories\Http\Resources\JsonApi\DirectoryResource;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;

final class DirectoryController extends Controller
{
    public function __construct(
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function store(DirectoryRequest $request): JsonResponse
    {
        $payload = $this->directoryService->create(DirectoryData::fromRequest($request));

        return new DirectoryResource($payload)
            ->response()
            ->setStatusCode(201);
    }

    public function update(DirectoryRequest $request, Directory $directory): DirectoryResource
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        return new DirectoryResource(
            $this->directoryService->update(
                data: DirectoryData::fromRequest($request, $directory),
                directory: $directory,
            ),
        );
    }

    public function destroy(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $directory->delete();

        return new JsonResponse(status: 204);
    }
}
