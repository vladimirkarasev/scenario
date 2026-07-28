<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;
use Module\Projects\Repositories\ProjectRepository;
use Module\Users\Models\User;

final readonly class DirectoryAccess
{
    public function __construct(private ProjectRepository $projects)
    {
    }

    public function projectFor(?User $user): Project
    {
        return $this->projects->activeBySitekeyAndHost($user?->sitekey, $user?->host)
            ?? throw DirectoryException::projectNotFound();
    }

    public function ensureDirectoryInProject(Directory $directory, Project $project): void
    {
        if ($directory->project_id !== $project->id) {
            throw DirectoryException::notInProject();
        }
    }
}
