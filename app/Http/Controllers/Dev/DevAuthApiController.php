<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Module\Users\Models\User;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Models\Role;

final class DevAuthApiController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokenService,
    ) {
    }

    /**
     * Симулирует прод-флоу: создаёт/обновляет пользователя проекта и выдаёт одноразовый _token.
     * Origin у dev-токена не ограничивается (в локали Origin ≠ project->host).
     */
    public function launch(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => ['required', 'string', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['string'],
        ]);

        /** @var Project $project */
        $project = Project::query()->findOrFail($request->string('project_id')->toString());

        $user = User::query()->updateOrCreate(
            ['project_id' => $project->id, 'login' => $request->string('login')->toString()],
            [
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'external_id' => $request->filled('external_id') ? $request->string('external_id')->toString() : null,
                'is_system' => false,
                'password' => Hash::make(Str::random(40)),
            ],
        );

        $roles = array_values(array_filter($request->array('roles'), is_string(...)));

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
        $user->syncRoles($roles);

        $result = $this->tokenService->createLaunchToken($user, $project, '');

        return response()->json(['_token' => $result['launch_token']]);
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
