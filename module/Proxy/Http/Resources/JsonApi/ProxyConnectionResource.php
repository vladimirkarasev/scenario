<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Services\CredentialCatalog;

/**
 * @mixin ProxyConnection
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
        $driver = CredentialCatalog::has($this->credential_type)
            ? CredentialCatalog::make($this->credential_type)
            : null;

        $secrets = $this->secrets ?? [];
        $secretFilled = [];
        foreach ($driver?->secretKeys() ?? [] as $key) {
            $secretFilled[$key] = filled($secrets[$key] ?? null);
        }

        return [
            'project_id' => $this->project_id,
            'name' => $this->name,
            'credential_type' => $this->credential_type,
            'credential_label' => $driver?->label() ?? $this->credential_type,
            'config' => $this->config ?? [], // несекретное
            'secret_filled' => $secretFilled, // секреты не отдаём, только флаг «заполнено»
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
