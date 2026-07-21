<?php

declare(strict_types=1);

namespace Module\Users\Services;

use Module\Projects\CurrentProject;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;

final readonly class CurrentUserService
{
    public function __construct(
        private CurrentProject $currentProject,
    ) {
    }

    /**
     * @return array{id: int, name: string, email: string, project_id: string|null, permissions: list<string>}
     */
    public function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'project_id' => $this->currentProject->id(),
            'permissions' => $this->permissionNames($user),
        ];
    }

    /** @return list<string> */
    private function permissionNames(User $user): array
    {
        $names = [];

        foreach ($user->getAllPermissions() as $permission) {
            if ($permission instanceof Permission) {
                $names[] = $permission->name;
            }
        }

        return $names;
    }
}
