<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Projects\CurrentProject;

final class CurrentUserController extends Controller
{
    public function __construct(
        private readonly CurrentProject $currentProject,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'project_id' => $this->currentProject->id(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }
}
