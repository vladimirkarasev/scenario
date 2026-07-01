<?php

declare(strict_types=1);

namespace Module\Projects\Http\Middleware;

use Module\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Module\Projects\CurrentProject;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireCurrentProject
{
    public function __construct(private CurrentProject $currentProject)
    {
    }

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->currentProject->exists(), 403, 'Контекст проекта не определён.');

        $user = $request->user();
        $projectId = $this->currentProject->id();

        abort_unless(
            $user instanceof User
            && $projectId !== null
            && $user->project_id === $projectId,
            403,
            'Пользователь не состоит в текущем проекте.',
        );

        return $next($request);
    }
}
