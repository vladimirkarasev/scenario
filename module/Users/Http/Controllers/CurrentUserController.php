<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Users\Models\User;
use Module\Users\Services\CurrentUserService;

final class CurrentUserController extends Controller
{
    public function __construct(
        private readonly CurrentUserService $service,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new ApiResponse($this->service->profile($user));
    }
}
