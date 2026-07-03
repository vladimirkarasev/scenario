<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Успешный JSON-ответ в едином конверте: полезная нагрузка всегда под `data`.
 * Блок `meta` (timestamp + requestId) добавляет middleware AddApiMeta.
 *
 * Для JSON:API ресурсов конверт `data` формирует сам ресурс — там этот класс
 * не нужен; ApiResponse предназначен для «плоских» полезных нагрузок.
 */
final class ApiResponse extends JsonResponse
{
    /** @param  array<string, mixed>  $meta */
    public function __construct(mixed $data, int $status = 200, array $meta = [])
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        parent::__construct($payload, $status);
    }
}
