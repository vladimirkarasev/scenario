<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\ApiMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Единый JSON:API-конверт ошибки: {"errors": [...], "meta": {...}}.
 */
final class ApiErrorResponse
{
    /** @param  list<array<string, mixed>>  $errors */
    public static function make(array $errors, int $status, Request $request): JsonResponse
    {
        return new JsonResponse([
            'errors' => $errors,
            'meta' => ApiMeta::for($request),
        ], $status, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}
