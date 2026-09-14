<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Proxy\DTO\ProxyConnectionView;

/**
 * @mixin ProxyConnectionView
 */
final class ProxyConnectionResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'proxy-connections';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'project_id' => $this->projectId,
            'name' => $this->name,
            'credential_type' => $this->credentialType,
            'credential_label' => $this->credentialLabel,
            'config' => $this->config,
            'secret_filled' => $this->secretFilled,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
