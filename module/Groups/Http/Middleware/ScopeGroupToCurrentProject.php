<?php

declare(strict_types=1);

namespace Module\Groups\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Module\Groups\Models\UserGroup;
use Module\Projects\CurrentProject;
use Symfony\Component\HttpFoundation\Response;

final readonly class ScopeGroupToCurrentProject
{
    public function __construct(private CurrentProject $currentProject)
    {
    }

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $group = $request->route('group');

        if (!$group instanceof UserGroup) {
            return $next($request);
        }

        $projectId = $this->currentProject->id();

        abort_unless(
            $projectId !== null && $group->site_id === $projectId,
            404,
        );

        return $next($request);
    }
}
