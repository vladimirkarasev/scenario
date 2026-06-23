<?php

declare(strict_types=1);

namespace Module\Projects;

use Module\Projects\Models\Project;

final readonly class CurrentProject
{
    public function __construct(
        private ?Project $project,
    ) {}

    public function get(): ?Project
    {
        return $this->project;
    }

    public function id(): ?string
    {
        return $this->project?->id;
    }

    public function exists(): bool
    {
        return $this->project !== null;
    }
}
