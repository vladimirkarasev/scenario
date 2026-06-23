<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\DTO\EmbedAuth\ProjectUserRegisterData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use App\Services\EmbedAuth\EmbedAuthUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Projects\Models\Project;

final class IframeAuthApiController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokenService,
        private readonly EmbedAuthUserService $userService,
    ) {
    }

    public function exchange(Request $request): JsonResponse
    {
        $request->validate([
            'project_uuid' => ['required', 'string'],
            'login' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string'],
        ]);

        /** @var Project|null $project */
        $project = Project::query()->where('id', $request->string('project_uuid'))->first();

        if ($project === null) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $secret = $request->bearerToken();

        if ($secret === null || $project->shared_secret === null || !hash_equals($project->shared_secret, $secret)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user = $this->userService->syncUser(
            new ProjectUserRegisterData(
                login: $request->string('login')->toString(),
                email: $request->filled('email') ? $request->string('email')->toString() : null,
                name: $request->string('name')->toString(),
                externalId: null,
                roles: array_values(
                    array_map(static fn(mixed $r): string => is_string($r) ? $r : '', $request->array('roles'))
                ),
            ),
            $project
        );

        return response()->json($this->tokenService->authorizeUser($user, $project));
    }

    public function mockToken(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'project_id' => ['required', 'string', 'exists:projects,id'],
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($request->integer('user_id'));
        /** @var Project $project */
        $project = Project::query()->findOrFail($request->string('project_id'));

        return response()->json(
            $this->tokenService->createLaunchToken($user, $project),
        );
    }

    public function devLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('dev-api')->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
