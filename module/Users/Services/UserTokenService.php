<?php

declare(strict_types=1);

namespace Module\Users\Services;

use Illuminate\Contracts\Events\Dispatcher;
use LogicException;
use Module\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Module\Projects\CurrentProject;
use Module\Users\Repositories\UserRepository;
use Module\Users\Events\SecurityEvent;

final readonly class UserTokenService
{
    public function __construct(
        private UserRepository $users,
        private CurrentProject $currentProject,
        private UserAuthorizationService $authorization,
        private Dispatcher $events,
    ) {
    }

    /** @return Collection<int, PersonalAccessToken> */
    public function list(User $actor, User $target): Collection
    {
        $target = $this->targetInProject($target);
        $this->authorization->assertMayManage($actor, $target);

        return $target->tokens()
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(
        User $actor,
        User $target,
        string $name,
        ?\DateTimeInterface $expiresAt,
    ): NewAccessToken {
        $target = $this->targetInProject($target);
        $this->authorization->assertMayManage($actor, $target);

        $token = $target->createToken($name, ['*'], $expiresAt);
        $this->events->dispatch(new SecurityEvent('user_token.created', $actor->id, [
            'project_id' => $target->project_id,
            'user_id' => $target->id,
            'token_id' => $token->accessToken->id,
            'expires_at' => $expiresAt?->format(DATE_ATOM),
        ]));

        return $token;
    }

    public function revoke(User $actor, User $target, int $tokenId): void
    {
        $target = $this->targetInProject($target);
        $this->authorization->assertMayManage($actor, $target);

        $deleted = $target->tokens()->whereKey($tokenId)->delete();

        if ($deleted > 0) {
            $this->events->dispatch(new SecurityEvent('user_token.revoked', $actor->id, [
                'project_id' => $target->project_id,
                'user_id' => $target->id,
                'token_id' => $tokenId,
            ]));
        }
    }

    private function targetInProject(User $target): User
    {
        $projectId = $this->currentProject->id()
            ?? throw new LogicException('Token operations require a current project.');

        return $this->users->findInProject($target, $projectId);
    }

}
