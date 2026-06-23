<?php

declare(strict_types=1);

namespace Module\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Projects\DTO\ProjectData;
use Module\Projects\Http\Requests\ProjectDestroyRequest;
use Module\Projects\Http\Requests\ProjectRequest;
use Module\Projects\Http\Resources\JsonApi\ProjectResource;
use Module\Projects\Models\Project;
use Module\Projects\Services\ProjectService;

final class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            $this->projectService->paginate(max(1, min(100, $request->integer('page.size', 20)))),
        );
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project);
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create(ProjectData::fromRequest($request));

        return new JsonResponse(new ProjectResource($project), 201);
    }

    public function update(ProjectRequest $request, Project $project): JsonResponse
    {
        $project = $this->projectService->update(ProjectData::fromRequest($request), $project);

        return new JsonResponse(new ProjectResource($project));
    }

    public function destroy(ProjectDestroyRequest $request, Project $project): JsonResponse
    {
        $this->projectService->delete($project);

        return new JsonResponse(status: 204);
    }
}
