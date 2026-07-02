<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;
use Module\Projects\CurrentProject;
use Module\Users\Enums\UserErrorCode;
use Module\Users\Http\Requests\LaunchTokenRequest;
use Module\Users\Repositories\UserRepository;

/**
 * Выпуск одноразового launch-токена (_token) для пользователя проекта по external_id.
 * Вызывается системным пользователем; фронт обменивает _token на пару access/refresh.
 */
final class LaunchTokenController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokens,
        private readonly CurrentProject $currentProject,
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(LaunchTokenRequest $request): JsonResponse
    {
        $project = $this->currentProject->get();

        if ($project === null) {
            throw ForbiddenException::make('Контекст проекта не определён.', UserErrorCode::NoProjectContext);
        }

        $user = $this->users->findNonSystemByExternalId(
            $project->id,
            $request->string('external_id')->toString(),
        );

        if ($user === null) {
            throw NotFoundException::make('Пользователь не найден в проекте.', UserErrorCode::UserNotFound, 'Пользователь не найден');
        }

        $result = $this->tokens->createLaunchToken($user, $project);

        return new ApiResponse([
            '_token' => $result['launch_token'],
            'expires_in' => $result['expires_in'],
        ]);
    }
}
