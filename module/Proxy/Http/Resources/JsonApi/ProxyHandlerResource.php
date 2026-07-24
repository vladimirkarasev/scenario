<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property array{class: string, label: string, group: string, credential_type: string|null, method: string} $resource
 */
final class ProxyHandlerResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return $this->resource['class'];
    }

    public function toType(Request $request): string
    {
        return 'proxy-handlers';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'label' => $this->resource['label'],
            'group' => $this->resource['group'],
            'credential_type' => $this->resource['credential_type'],
            'method' => $this->resource['method'],
        ];
    }
}
