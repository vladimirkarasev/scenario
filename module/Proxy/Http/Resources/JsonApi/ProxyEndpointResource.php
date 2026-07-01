<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Support\IntegrationCredentials;

/**
 * @mixin ProxyEndpoint
 */
final class ProxyEndpointResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'proxy-endpoints';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        $masked = IntegrationCredentials::mask($this->credentials ?? []);

        return [
            'project_id' => $this->project_id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'method' => $this->method,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_mocked' => $this->is_mocked,
            'handler_class' => $this->handler_class,
            'connection_id' => $this->connection_id,
            'category_ids' => $this->whenLoaded(
                'categories',
                fn (): array => $this->categories->pluck('id')->all(),
                [],
            ),
            'base_uri' => $this->base_uri,
            'credentials' => $masked['credentials'],
            'secret_filled' => $masked['secret_filled'],
            'config' => $this->config ?? [],
            'mock_responses' => $this->mock_responses ?? [],
            'receive_url' => route('proxy.proxies.receive', $this->uuid),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
