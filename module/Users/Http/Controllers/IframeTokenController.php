<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Illuminate\Http\JsonResponse;
use Module\Projects\CurrentProject;
use Module\Users\Http\Requests\IframeTokenRequest;
use Module\Users\Repositories\UserRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Выпуск одноразового iframe-токена (_token) для пользователя проекта по external_id.
 * Вызывается системным пользователем; фронт обменивает _token на пару access/refresh.
 */
final class IframeTokenController extends Controller
{
    public function __construct(
        private readonly EmbedAuthTokenService $tokens,
        private readonly CurrentProject $currentProject,
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(IframeTokenRequest $request): JsonResponse
    {
        $project = $this->currentProject->get();

        if ($project === null) {
            throw new HttpException(403, 'Контекст проекта не определён.');
        }

        $user = $this->users->findNonSystemByExternalId(
            $project->id,
            $request->string('external_id')->toString(),
        );

        if ($user === null) {
            throw new HttpException(404, 'Пользователь не найден в проекте.');
        }

        $result = $this->tokens->createLaunchToken($user, $project);

        return new JsonResponse([
            '_token' => $result['iframe_launch_token'],
            'expires_in' => $result['expires_in'],
        ]);
    }
}
