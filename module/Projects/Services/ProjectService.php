<?php

declare(strict_types=1);

namespace Module\Projects\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Projects\DTO\ProjectData;
use Module\Projects\Models\Project;
use Module\Projects\Repositories\ProjectRepository;

final readonly class ProjectService
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->projects->paginate($perPage);
    }

    public function create(ProjectData $data): Project
    {
        return $this->projects->create($data->toAttributes());
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
