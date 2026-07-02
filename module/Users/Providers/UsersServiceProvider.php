<?php

declare(strict_types=1);

namespace Module\Users\Providers;

use App\Exceptions\DomainException;
use App\Http\Middleware\AddApiMeta;
use App\Http\Responses\ApiErrorResponse;
use App\Support\PermissionRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Module\Projects\Http\Middleware\RequireCurrentProject;
use Module\Projects\Http\Middleware\ScopeUserToCurrentProject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Module\Users\Enums\RolePermission;
use Module\Users\Enums\UserPermission;
use Module\Users\Events\UserCreated;
use Module\Users\Events\UserDeleted;
use Module\Users\Events\UserUpdated;
use Module\Users\Events\SecurityEvent;
use Module\Users\Listeners\LogUserAudit;
use Module\Users\Listeners\LogSecurityAudit;
use Module\Projects\Models\Project;
use Module\Users\Services\ProjectUserCleanupService;

final class UsersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PermissionRegistry::register(UserPermission::class, RolePermission::class);

        Event::listen(UserCreated::class, [LogUserAudit::class, 'created']);
        Event::listen(UserUpdated::class, [LogUserAudit::class, 'updated']);
        Event::listen(UserDeleted::class, [LogUserAudit::class, 'deleted']);
        Event::listen(SecurityEvent::class, LogSecurityAudit::class);

        Project::deleting(static function (Project $project): void {
            app(ProjectUserCleanupService::class)->handle($project);
        });

        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        Route::middleware([
            'api',
            'auth:sanctum',
            RequireCurrentProject::class,
            ScopeUserToCurrentProject::class,
            AddApiMeta::class,
        ])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');

        Route::middleware(['api', 'auth:sanctum', AddApiMeta::class])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/account.php');

        $this->registerExceptionHandlers();
    }

    /**
     * Единый JSON:API-формат ошибок. Доменные исключения рендерятся всегда;
     * стандартные исключения фреймворка — только на маршрутах модуля Users,
     * чтобы не менять поведение остальных модулей.
     */
    private function registerExceptionHandlers(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(
            fn(DomainException $e, Request $request): ?JsonResponse => $request->is('api/*')
                ? ApiErrorResponse::make([$e->toError()], $e->status(), $request)
                : null
        );

        $handler->renderable(function (ValidationException $e, Request $request): ?JsonResponse {
            if (!$this->isUsersApiRoute($request)) {
                return null;
            }

            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $errors[] = [
                        'status' => '422',
                        'code' => 'VALIDATION_ERROR',
                        'title' => 'Ошибка валидации',
                        'detail' => is_string($message) ? $message : '',
                        'source' => ['pointer' => '/data/attributes/'.$field],
                    ];
                }
            }

            return ApiErrorResponse::make($errors, 422, $request);
        });

        $handler->renderable(
            fn(ModelNotFoundException $e, Request $request): ?JsonResponse => $this->isUsersApiRoute($request)
                ? ApiErrorResponse::make([[
                    'status' => '404',
                    'code' => 'NOT_FOUND',
                    'title' => 'Не найдено',
                    'detail' => 'Запрошенный ресурс не найден.',
                ]], 404, $request)
                : null
        );

        $handler->renderable(
            fn(AuthorizationException|AccessDeniedHttpException $e, Request $request): ?JsonResponse => $this->isUsersApiRoute($request)
                ? ApiErrorResponse::make([[
                    'status' => '403',
                    'code' => 'FORBIDDEN',
                    'title' => 'Доступ запрещён',
                    'detail' => $e->getMessage() !== '' ? $e->getMessage() : 'Недостаточно прав.',
                ]], 403, $request)
                : null
        );

        $handler->renderable(
            fn(AuthenticationException $e, Request $request): ?JsonResponse => $this->isUsersApiRoute($request)
                ? ApiErrorResponse::make([[
                    'status' => '401',
                    'code' => 'UNAUTHENTICATED',
                    'title' => 'Не аутентифицирован',
                    'detail' => $e->getMessage() !== '' ? $e->getMessage() : 'Требуется аутентификация.',
                ]], 401, $request)
                : null
        );

        // Общий фолбэк для прочих HTTP-исключений (abort() из middleware, 405, 429 и т.п.).
        // Регистрируется последним — специфичные обработчики выше имеют приоритет.
        $handler->renderable(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if (!$this->isUsersApiRoute($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            [$code, $title] = $this->httpErrorMeta($status);

            return ApiErrorResponse::make([[
                'status' => (string) $status,
                'code' => $code,
                'title' => $title,
                'detail' => $e->getMessage() !== '' ? $e->getMessage() : $title,
            ]], $status, $request);
        });
    }

    /** @return array{string, string} */
    private function httpErrorMeta(int $status): array
    {
        return match ($status) {
            400 => ['BAD_REQUEST', 'Некорректный запрос'],
            401 => ['UNAUTHENTICATED', 'Не аутентифицирован'],
            403 => ['FORBIDDEN', 'Доступ запрещён'],
            404 => ['NOT_FOUND', 'Не найдено'],
            405 => ['METHOD_NOT_ALLOWED', 'Метод не разрешён'],
            422 => ['UNPROCESSABLE', 'Некорректный запрос'],
            429 => ['TOO_MANY_REQUESTS', 'Слишком много запросов'],
            default => ['HTTP_ERROR', 'Ошибка запроса'],
        };
    }

    private function isUsersApiRoute(Request $request): bool
    {
        $action = $request->route()?->getActionName() ?? '';

        return str_starts_with($action, 'Module\\Users\\Http\\Controllers\\');
    }
}
