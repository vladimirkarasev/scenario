<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Projects\CurrentProject;
use Module\Users\Models\User;

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

        return new ApiResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'project_id' => $this->currentProject->id(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }
}
