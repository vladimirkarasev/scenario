<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

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
