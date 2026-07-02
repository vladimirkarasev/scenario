<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Module\Users\Http\Requests\LoginRequest;
use Module\Users\Models\User;
use Module\Users\Repositories\UserRepository;

final class TokenAuthController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EmbedAuthTokenService $tokens,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        abort_unless(config('dev_auth.enabled', false) === true, 404);

        $projectId = $request->filled('project_id')
            ? $request->string('project_id')->toString()
            : null;
        $users = $this->users
            ->loginCandidates($request->string('email')->toString(), $projectId)
            ->filter(
                static fn (User $user): bool => Hash::check(
                    $request->string('password')->toString(),
                    $user->password,
                ),
            );

        if ($users->count() > 1) {
            return new JsonResponse([
                'message' => 'Для этого email необходимо указать project_id.',
            ], 422);
        }

        $user = $users->first();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Invalid credentials.'], 401);
        }

        $token = $user->createToken(
            'dev-api',
            ['*'],
            now()->addHours(8),
        )->plainTextToken;

        return new JsonResponse(['token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            $this->tokens->logout($bearer);
        }

        return new JsonResponse(['message' => 'Logged out.']);
    }
}
