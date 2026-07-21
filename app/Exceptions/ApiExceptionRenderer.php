<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\AddApiMeta;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(
            fn(DomainException $e, Request $request): ?JsonResponse => $request->is('api/*')
                ? ApiErrorResponse::make([$e->toError()], $e->status(), $request)
                : null
        );

        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (!self::isUnifiedRoute($request)) {
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

        $exceptions->render(
            fn(ModelNotFoundException $e, Request $request): ?JsonResponse => self::isUnifiedRoute($request)
                ? ApiErrorResponse::make([[
                    'status' => '404',
                    'code' => 'NOT_FOUND',
                    'title' => 'Не найдено',
                    'detail' => 'Запрошенный ресурс не найден.',
                ]], 404, $request)
                : null
        );

        $exceptions->render(
            fn(AuthorizationException|AccessDeniedHttpException $e, Request $request): ?JsonResponse => self::isUnifiedRoute($request)
                ? ApiErrorResponse::make([[
                    'status' => '403',
                    'code' => 'FORBIDDEN',
                    'title' => 'Доступ запрещён',
                    'detail' => $e->getMessage() !== '' ? $e->getMessage() : 'Недостаточно прав.',
                ]], 403, $request)
                : null
        );

        $exceptions->render(
            fn(AuthenticationException $e, Request $request): ?JsonResponse => self::isUnifiedRoute($request)
                ? ApiErrorResponse::make([[
                    'status' => '401',
                    'code' => 'UNAUTHENTICATED',
                    'title' => 'Не аутентифицирован',
                    'detail' => $e->getMessage() !== '' ? $e->getMessage() : 'Требуется аутентификация.',
                ]], 401, $request)
                : null
        );

        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if (!self::isUnifiedRoute($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            [$code, $title] = self::httpErrorMeta($status);

            return ApiErrorResponse::make([[
                'status' => (string) $status,
                'code' => $code,
                'title' => $title,
                'detail' => $e->getMessage() !== '' ? $e->getMessage() : $title,
            ]], $status, $request);
        });
    }

    private static function isUnifiedRoute(Request $request): bool
    {
        $route = $request->route();

        return $route !== null && in_array(AddApiMeta::class, $route->gatherMiddleware(), true);
    }

    /** @return array{string, string} */
    private static function httpErrorMeta(int $status): array
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
}
