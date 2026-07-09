<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property array<string, mixed> $resource
 */
final class ProxyFeedResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        $id = $this->resource['id'] ?? '';

        return is_scalar($id) ? (string) $id : '';
    }

    public function toType(Request $request): string
    {
        return ($this->resource['type'] ?? null) === 'folder'
            ? 'proxy-folders'
            : 'proxy-endpoints';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return array_diff_key($this->resource, ['id' => null, 'type' => null]);
    }
}
