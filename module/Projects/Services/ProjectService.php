<?php

declare(strict_types=1);

namespace Module\Projects\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Module\Projects\DTO\ProjectData;
use Module\Projects\Models\Project;
use Module\Projects\Repositories\ProjectRepository;
use Module\Users\Services\SystemUserService;

final readonly class ProjectService
{
    public function __construct(
        private ProjectRepository $projects,
        private SystemUserService $systemUsers,
    ) {
    }

    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->projects->paginate($perPage);
    }

    public function create(ProjectData $data): Project
    {
        return DB::transaction(function () use ($data): Project {
            $project = $this->projects->create($data->toAttributes());
            $this->systemUsers->provision($project);

            return $project;
        });
    }

    public function update(ProjectData $data, Project $project): Project
    {
        return $this->projects->update($project, $data->toAttributes());
    }

    public function delete(Project $project): void
    {
        $this->projects->delete($project);
    }
}
