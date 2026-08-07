<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Database\Seeders\DevSeeder;
use Database\Seeders\Fixtures\DevProject;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Module\Projects\Models\Project;
use Module\Users\Models\User;

final class DevAuthWebController extends Controller
{
    public function __invoke(): Response
    {
        $projects = Project::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'sitekey']);

        $users = User::query()
            ->with('roles:id,name')
            ->whereIn('project_id', $projects->modelKeys())
            ->where('is_system', false)
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'fio', 'login', 'email']);

        $usersByProject = $users->groupBy('project_id');
        $projectOptions = $projects->map(static function (Project $project) use ($usersByProject): array {
            $projectUsers = $usersByProject->get($project->id, new Collection());

            return [
                'id' => $project->id,
                'name' => $project->name,
                'host' => $project->host,
                'users' => $projectUsers->map(static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->fio ?: $user->name,
                    'login' => $user->login,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->values()->all(),
                ])->values()->all(),
            ];
        })->values();

        $defaultProject = $projects->firstWhere('sitekey', DevProject::Alfa->value)
            ?? $projects->first();
        $defaultProjectUsers = $defaultProject === null
            ? new Collection()
            : $usersByProject->get($defaultProject->id, new Collection());
        $defaultUser = $defaultProjectUsers->firstWhere('login', DevSeeder::ADMIN_LOGIN)
            ?? $defaultProjectUsers->first();

        return Inertia::render('Auth/DevAuth', [
            'projects' => $projectOptions,
            'defaultProjectId' => $defaultProject?->id,
            'defaultUserId' => $defaultUser?->id,
        ]);
    }
}
