<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiMeta;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddApiMeta
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300 || $status === 204) {
            return $response;
        }

        $data = $response->getData(true);
        if (!is_array($data)) {
            return $response;
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $data['meta'] = array_merge($meta, ApiMeta::for($request));

        $response->setData($data);

        return $response;
    }
}
