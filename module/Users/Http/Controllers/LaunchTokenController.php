<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Module\Users\Http\Requests\LaunchTokenRequest;
use Module\Users\Services\LaunchTokenService;

/**
 * Выпуск одноразового launch-токена (_token) для пользователя проекта по external_id.
 * Вызывается системным пользователем; фронт обменивает _token на пару access/refresh.
 */
final class LaunchTokenController extends Controller
{
    public function __construct(
        private readonly LaunchTokenService $service,
    ) {
    }

    public function __invoke(LaunchTokenRequest $request): JsonResponse
    {
        return new ApiResponse(
            $this->service->issue($request->string('external_id')->toString()),
        );
    }
}
