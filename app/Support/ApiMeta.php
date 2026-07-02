<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Формирует блок meta, обязательный для каждого API-ответа:
 * timestamp запроса и сквозной requestId (из SetRequestId middleware).
 */
final class ApiMeta
{
    /** @return array{timestamp: string, requestId: string} */
    public static function for(Request $request): array
    {
        $requestId = $request->attributes->get('request_id');

        if (!is_string($requestId) || $requestId === '') {
            $requestId = $request->headers->get('X-Request-Id') ?? '';
        }

        return [
            'timestamp' => now()->toIso8601String(),
            'requestId' => $requestId,
        ];
    }
}
