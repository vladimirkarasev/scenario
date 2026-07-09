<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property array<string, mixed> $resource
 */
final class ProxyFieldResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        $key = $this->resource['key'] ?? '';

        return is_string($key) ? $key : '';
    }

    public function toType(Request $request): string
    {
        return 'proxy-fields';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return $this->resource;
    }
}
