<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Middleware;

use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Enums\ScenarioPermission;
use Module\Users\Models\User;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireScenarioServiceAccount
{
    /**
     * Разрешает либо сервисный аккаунт проекта (внешние интеграции, Bearer-токен),
     * либо CMS-пользователя с правом на создание/редактирование сценариев (превью из редактора).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_system && $this->hasBearerToken($request)) {
            return $next($request);
        }

        if ($user instanceof User && $user->can(ScenarioPermission::Create->value)) {
            return $next($request);
        }

        throw ForbiddenException::from(ScenarioErrorCode::ServiceAccountRequired);
    }

    private function hasBearerToken(Request $request): bool
    {
        $bearer = $request->bearerToken();

        return is_string($bearer) && $bearer !== '';
    }
}
