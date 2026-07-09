<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Middleware;

use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Users\Models\User;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireScenarioServiceAccount
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $bearer = $request->bearerToken();

        if (
            !$user instanceof User
            || !$user->is_system
            || !is_string($bearer)
            || $bearer === ''
        ) {
            throw ForbiddenException::from(ScenarioErrorCode::ServiceAccountRequired);
        }

        return $next($request);
    }
}
