<?php

declare(strict_types=1);

namespace Module\Users\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Services\EmbedAuth\EmbedAuthTokenService;
use Module\Projects\CurrentProject;
use Module\Users\Enums\UserErrorCode;
use Module\Users\Repositories\UserRepository;

final readonly class LaunchTokenService
{
    public function __construct(
        private EmbedAuthTokenService $tokens,
        private CurrentProject $currentProject,
        private UserRepository $users,
    ) {
    }

    /**
     * @return array{_token: string, expires_in: int}
     */
    public function issue(string $externalId): array
    {
        $project = $this->currentProject->get();

        if ($project === null) {
            throw ForbiddenException::from(UserErrorCode::NoProjectContext);
        }

        $user = $this->users->findNonSystemByExternalId($project->id, $externalId);

        if ($user === null) {
            throw NotFoundException::from(UserErrorCode::UserNotFound);
        }

        $result = $this->tokens->createLaunchToken($user, $project);

        return [
            '_token' => (string) $result['launch_token'],
            'expires_in' => (int) $result['expires_in'],
        ];
    }
}
