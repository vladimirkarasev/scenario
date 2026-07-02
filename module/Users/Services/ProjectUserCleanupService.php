<?php

declare(strict_types=1);

namespace Module\Users\Services;

use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Module\Users\Repositories\UserRepository;

final readonly class ProjectUserCleanupService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function handle(Project $project): void
    {
        $this->users
            ->inProjectLazily($project->id)
            ->each(static function (User $user): void {
                $user->tokens()->delete();
                $user->delete();
            });
    }
}
