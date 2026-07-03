<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property array{type: string, label: string, group: string, fields: list<array<string, mixed>>} $resource
 */
final class ProxyCredentialTypeResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource['type'];
    }

    public function toType(Request $request): string
    {
        return 'proxy-credential-types';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'label' => $this->resource['label'],
            'group' => $this->resource['group'],
            'fields' => $this->resource['fields'],
        ];
    }
}
