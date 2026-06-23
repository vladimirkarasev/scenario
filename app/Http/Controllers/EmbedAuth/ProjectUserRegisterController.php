<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmbedAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmbedAuth\ProjectUserRegisterRequest;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use App\Services\EmbedAuth\EmbedAuthUserService;
use Illuminate\Http\JsonResponse;
use Module\Projects\Models\Project;

final class ProjectUserRegisterController extends Controller
{
    public function __construct(
        private readonly EmbedAuthUserService $userService,
        private readonly EmbedAuthTokenService $tokenService,
    ) {
    }

    public function __invoke(ProjectUserRegisterRequest $request, string $projectUuid): JsonResponse
    {
        $project = Project::query()->where('uuid', $projectUuid)->first();

        if ($project === null) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        if (!$project->is_active) {
            return response()->json(['message' => 'Project is not active.'], 422);
        }

        if (!$this->authorizeSecret($request, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user = $this->userService->syncUser($request->toData(), $project);

        return response()->json($this->tokenService->createLaunchToken($user, $project));
    }

    private function authorizeSecret(ProjectUserRegisterRequest $request, Project $project): bool
    {
        $secret = $request->bearerToken();

        if ($secret === null || $project->shared_secret === null) {
            return false;
        }

        return hash_equals($project->shared_secret, $secret);
    }
}
